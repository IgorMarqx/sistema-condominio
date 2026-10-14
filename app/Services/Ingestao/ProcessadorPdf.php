<?php

namespace App\Services\Ingestao;

use App\Enums\Concessionaria;
use App\Exceptions\RegraNegocio;
use App\Models\Anexo;
use App\Models\CaixaEntrada;
use App\Models\EmailRecebido;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Models\Unidade;
use App\Services\Arquivos\Armazenamento;
use App\Services\Auditoria\Auditoria;
use App\Services\Faturas\FaturaService;
use App\Services\Ingestao\Extratores\CagepaExtractor;
use App\Services\Ingestao\Extratores\EnergisaExtractor;
use App\Services\Ingestao\Extratores\FaturaExtractor;
use App\Services\Notificador;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Etapas 4 a 9 do pipeline para um PDF: deduplicação, abertura, extração, vínculo e registro.
 * Serve ao e-mail e ao upload manual.
 */
class ProcessadorPdf
{
    /**
     * @var list<FaturaExtractor>
     */
    private array $extratores;

    public function __construct(
        private readonly Armazenamento $armazenamento,
        private readonly LeitorPdf $leitor,
        private readonly FaturaService $faturas,
        private readonly Notificador $notificador,
        private readonly Auditoria $auditoria,
    ) {
        $this->extratores = [new EnergisaExtractor, new CagepaExtractor];
    }

    /**
     * @param  Unidade|null  $dono  null = condomínio
     * @param  array<string, mixed>  $digitado  campos digitados no upload (valem mais que os extraídos)
     */
    public function processar(
        string $conteudo,
        string $nome,
        ?Unidade $dono,
        ?EmailRecebido $email = null,
        ?Medidor $medidorEscolhido = null,
        array $digitado = [],
        ?int $enviadoPor = null,
    ): ResultadoPdf {
        $sha = hash('sha256', $conteudo);
        $existente = Anexo::query()->where('sha256', $sha)->first();

        if ($existente) {
            return ResultadoPdf::duplicado(Fatura::query()->where('anexo_id', $existente->id)->first());
        }

        $arquivo = $this->armazenamento->guardarConteudo($conteudo, 'faturas', 'pdf');
        $anexo = Anexo::query()->create([
            'email_id' => $email?->id,
            'nome' => $this->armazenamento->nomeSeguro($nome),
            's3_key' => $arquivo['caminho'],
            'sha256' => $sha,
            'tamanho' => $arquivo['tamanho'],
            'mime' => 'application/pdf',
            'enviado_por' => $enviadoPor,
        ]);

        try {
            return $this->depoisDoAnexo($anexo, $conteudo, $dono, $email, $medidorEscolhido, $digitado);
        } catch (RegraNegocio $e) {
            return ResultadoPdf::revisao($e->getMessage());
        } catch (Throwable $e) {
            // Falha inesperada: desfaz o anexo para a nova tentativa da fila não achar "duplicado".
            if (! Fatura::query()->where('anexo_id', $anexo->id)->exists()) {
                $anexo->delete();
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $digitado
     */
    private function depoisDoAnexo(Anexo $anexo, string $conteudo, ?Unidade $dono, ?EmailRecebido $email, ?Medidor $medidorEscolhido, array $digitado): ResultadoPdf
    {
        $senhas = $this->senhasDoDono($dono, $medidorEscolhido);
        $texto = null;
        $dados = null;

        try {
            $texto = $this->leitor->texto($conteudo, $senhas);
            $extrator = collect($this->extratores)->first(fn (FaturaExtractor $e) => $e->supports($texto));
            $dados = $extrator?->extract($texto);
        } catch (PdfProtegido $e) {
            if ($digitado === []) {
                return ResultadoPdf::revisao($e->getMessage().' Cadastre a senha no medidor ou envie pelo upload digitando os campos.');
            }
        } catch (Throwable $e) {
            report($e);

            if ($digitado === []) {
                return ResultadoPdf::revisao('Não foi possível ler o PDF ('.$e->getMessage().').');
            }
        }

        if ($dados === null && $medidorEscolhido === null) {
            return ResultadoPdf::revisao('O PDF não parece uma fatura da Energisa nem da Cagepa.');
        }

        $dados ??= new DadosFatura($medidorEscolhido->concessionaria, extrator: 'manual', versao: '0');

        $medidor = $medidorEscolhido ?? $this->localizarMedidor($dados, $dono, $email?->caixaEntrada);

        if ($medidor instanceof ResultadoPdf) {
            return $medidor;
        }

        if ($medidor->concessionaria !== $dados->concessionaria) {
            return ResultadoPdf::revisao('O PDF é da '.$dados->concessionaria->rotulo().', mas o medidor escolhido é da '.$medidor->concessionaria->rotulo().'.');
        }

        if ($medidorEscolhido && $dados->identificador && Medidor::normalizarIdentificador($dados->identificador) !== Medidor::normalizarIdentificador($medidor->identificador)) {
            return ResultadoPdf::revisao('O PDF é do medidor '.$dados->identificador.', diferente do escolhido ('.$medidor->identificador.').');
        }

        $mesRef = isset($digitado['mes_ref']) ? Carbon::parse($digitado['mes_ref'])->startOfMonth() : $dados->mesRef;

        if ($mesRef === null) {
            return ResultadoPdf::revisao('Não encontrei a competência (mês de referência) no PDF.');
        }

        unset($digitado['mes_ref']);

        $fatura = $this->faturas->registrar(
            $medidor,
            $dados,
            $mesRef,
            $anexo,
            $email,
            $email ? 'email' : 'upload',
            $texto,
            $digitado,
        );

        return ResultadoPdf::fatura($fatura);
    }

    /**
     * Etapa 7: nº da UC / matrícula → medidor cadastrado do dono certo.
     */
    private function localizarMedidor(DadosFatura $dados, ?Unidade $dono, ?CaixaEntrada $caixa): Medidor|ResultadoPdf
    {
        $donoTipo = $dono ? 'unidade' : 'condominio';

        if ($dados->identificador === null) {
            $candidatos = Medidor::query()
                ->where('dono_tipo', $donoTipo)
                ->where('unidade_id', $dono?->id)
                ->where('concessionaria', $dados->concessionaria)
                ->where('ativo', true)
                ->get();

            return $candidatos->count() === 1
                ? $candidatos->first()
                : ResultadoPdf::revisao('Não encontrei o nº '.($dados->concessionaria === Concessionaria::Energisa ? 'da UC' : 'da matrícula').' no PDF.');
        }

        $normalizado = Medidor::normalizarIdentificador($dados->identificador);
        $medidor = Medidor::query()
            ->where('concessionaria', $dados->concessionaria)
            ->get()
            ->first(fn (Medidor $m) => Medidor::normalizarIdentificador($m->identificador) === $normalizado);

        if ($medidor) {
            $mesmoDono = $medidor->dono_tipo === $donoTipo && $medidor->unidade_id === $dono?->id;

            if (! $mesmoDono) {
                $onde = $dono ? 'do apto '.$dono->numero : 'do condomínio';
                $deQuem = $medidor->isCondominio() ? 'do condomínio' : 'do apto '.$medidor->unidade?->numero;
                $motivo = 'A fatura do medidor '.$dados->identificador.' ('.$deQuem.') chegou no endereço '.$onde.'. Bloqueada.';

                $this->notificador->admin('medidor_outra_unidade', 'Fatura bloqueada: medidor de outro dono', $motivo, '/gestao/faturas', null);
                $this->auditoria->registrar('fatura.bloqueada', $medidor, null, ['caixa' => $caixa?->endereco, 'motivo' => $motivo]);

                return ResultadoPdf::bloqueado($motivo);
            }

            return $medidor;
        }

        if (! $dono) {
            return ResultadoPdf::revisao('O medidor '.$dados->identificador.' ('.$dados->concessionaria->rotulo().') não está cadastrado no condomínio.');
        }

        // Unidade: a primeira fatura cadastra o medidor dela. O síndico é avisado.
        $medidor = Medidor::query()->create([
            'dono_tipo' => 'unidade',
            'unidade_id' => $dono->id,
            'concessionaria' => $dados->concessionaria,
            'identificador' => $dados->identificador,
            'descricao' => 'Cadastrado pela primeira fatura',
            'ativo' => true,
        ]);

        $this->auditoria->registrar('medidor.cadastrado_automaticamente', $medidor, null, [
            'unidade' => $dono->numero,
            'concessionaria' => $dados->concessionaria->value,
            'identificador' => $dados->identificador,
        ]);

        $this->notificador->admin(
            'medidor_novo',
            'Novo medidor do apto '.$dono->numero,
            $dados->concessionaria->rotulo().' '.$dados->identificador.' cadastrado pela primeira fatura recebida.',
            '/gestao/medidores',
            'medidor-novo:'.$medidor->id,
        );

        return $medidor;
    }

    /**
     * @return list<string>
     */
    private function senhasDoDono(?Unidade $dono, ?Medidor $escolhido): array
    {
        $medidores = $escolhido
            ? collect([$escolhido])
            : Medidor::query()->where('dono_tipo', $dono ? 'unidade' : 'condominio')->where('unidade_id', $dono?->id)->get();

        return array_values($medidores->pluck('senha_pdf')->filter(fn ($s) => is_string($s) && $s !== '')->unique()->map(fn ($s) => (string) $s)->all());
    }
}
