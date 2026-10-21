<?php

namespace App\Services\Faturas;

use App\Enums\Concessionaria;
use App\Enums\StatusCompetencia;
use App\Enums\StatusFatura;
use App\Exceptions\RegraNegocio;
use App\Models\Anexo;
use App\Models\ContaCaixa;
use App\Models\EmailRecebido;
use App\Models\Fatura;
use App\Models\LancamentoCaixa;
use App\Models\Medidor;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Arquivos\Armazenamento;
use App\Services\Auditoria\Auditoria;
use App\Services\Caixa\CaixaService;
use App\Services\Configuracoes;
use App\Services\Ingestao\DadosFatura;
use App\Services\Notificador;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FaturaService
{
    /**
     * Campos que o síndico (ou a unidade, na conta individual) pode corrigir na revisão.
     */
    public const CAMPOS_EDITAVEIS = [
        'valor_centavos', 'vencimento', 'emissao', 'consumo', 'leitura_anterior', 'leitura_atual',
        'proxima_leitura', 'linha_digitavel', 'pix_copia_cola', 'chave_nf3e',
    ];

    public function __construct(
        private readonly CompetenciaService $competencias,
        private readonly ValidadorFatura $validador,
        private readonly Auditoria $auditoria,
        private readonly Notificador $notificador,
        private readonly Configuracoes $configuracoes,
        private readonly CaixaService $caixa,
        private readonly Armazenamento $armazenamento,
    ) {}

    /**
     * Grava a fatura extraída (ou digitada) e decide o status pelas verificações automáticas.
     *
     * @param  array<string, mixed>  $sobrescrever  campos digitados, que valem mais que os extraídos
     */
    public function registrar(
        Medidor $medidor,
        DadosFatura $dados,
        Carbon $mesRef,
        ?Anexo $anexo,
        ?EmailRecebido $email,
        string $origem,
        ?string $texto = null,
        array $sobrescrever = [],
    ): Fatura {
        return DB::transaction(function () use ($medidor, $dados, $mesRef, $anexo, $email, $origem, $texto, $sobrescrever) {
            $competencia = $this->competencias->garantir($medidor, $mesRef);

            $fatura = new Fatura([
                ...$dados->paraFatura(),
                ...array_filter($sobrescrever, fn ($v) => $v !== null && $v !== ''),
                'competencia_id' => $competencia->id,
                'medidor_id' => $medidor->id,
                'anexo_id' => $anexo?->id,
                'email_id' => $email?->id,
                'origem' => $origem,
                'texto_extraido' => $texto,
                'unidade_medida' => $medidor->concessionaria->unidadeMedida(),
            ]);

            // Fatura digitada à mão (upload sem leitura do PDF) não tem confiança de extração.
            if ($sobrescrever !== [] && $dados->confianca() < ValidadorFatura::CONFIANCA_MINIMA) {
                $fatura->confianca = null;
            }

            if ($fatura->chave_nf3e && Fatura::query()->where('chave_nf3e', $fatura->chave_nf3e)->exists()) {
                throw new RegraNegocio('Esta fatura (chave NF3e '.$fatura->chave_nf3e.') já está no sistema.');
            }

            $fatura->setRelation('medidor', $medidor);
            $fatura->setRelation('competencia', $competencia);
            $this->aplicarValidacao($fatura);
            $fatura->save();

            $this->competencias->aposFatura($fatura);

            $this->auditoria->registrar('fatura.recebida', $fatura, null, [
                'medidor' => $medidor->rotulo(),
                'competencia' => $competencia->mes_ref->format('Y-m'),
                'origem' => $origem,
                'valor_centavos' => $fatura->valor_centavos,
                'vencimento' => $fatura->vencimento?->toDateString(),
                'status' => $fatura->status->value,
                'anexo_sha256' => $anexo?->sha256,
                'extrator' => $fatura->extrator ? $fatura->extrator.'@'.$fatura->extrator_versao : null,
            ]);

            $this->avisarChegada($fatura);

            return $fatura;
        });
    }

    /**
     * Correção de campo na revisão: o PDF continua a fonte da verdade, a correção fica auditada com o motivo.
     *
     * @param  array<string, mixed>  $campos
     */
    public function corrigir(Fatura $fatura, array $campos, string $motivo, User $autor): Fatura
    {
        $this->garantirEditavel($fatura);

        $campos = array_intersect_key($campos, array_flip(self::CAMPOS_EDITAVEIS));

        if (trim($motivo) === '') {
            throw new RegraNegocio('Informe o motivo da correção.');
        }

        return DB::transaction(function () use ($fatura, $campos, $motivo) {
            $antes = $fatura->only(array_keys($campos));
            $fatura->fill($campos);
            $alterados = array_keys($fatura->getDirty());

            if ($alterados === []) {
                return $fatura;
            }

            $this->aplicarValidacao($fatura);
            $fatura->save();
            $this->competencias->aposFatura($fatura);

            $this->auditoria->registrar('fatura.corrigida', $fatura, $this->serializar(array_intersect_key($antes, array_flip($alterados))), [
                ...$this->serializar($fatura->only($alterados)),
                'motivo' => $motivo,
            ]);

            return $fatura->refresh();
        });
    }

    /**
     * O síndico confere a fatura do condomínio com o PDF. Só fatura confirmada entra em rateio.
     */
    public function confirmar(Fatura $fatura, User $autor): Fatura
    {
        if (! $fatura->isCondominio()) {
            throw new RegraNegocio('Só a fatura do condomínio passa pela confirmação do síndico.');
        }

        if (! in_array($fatura->status, [StatusFatura::PendenteConfirmacao, StatusFatura::PendenteRevisao], true)) {
            throw new RegraNegocio('Esta fatura não está aguardando confirmação.');
        }

        if ($fatura->valor_centavos === null || $fatura->vencimento === null) {
            throw new RegraNegocio('Corrija o valor e o vencimento antes de confirmar.');
        }

        if (Fatura::query()->where('competencia_id', $fatura->competencia_id)->where('status', StatusFatura::Confirmada)->whereKeyNot($fatura->id)->exists()) {
            throw new RegraNegocio('Já existe outra fatura confirmada nesta competência. Rejeite uma delas.');
        }

        return DB::transaction(function () use ($fatura, $autor) {
            $fatura->update([
                'status' => StatusFatura::Confirmada,
                'confirmada_por' => $autor->id,
                'confirmada_em' => now(),
            ]);

            $fatura->competencia->avancarPara(StatusCompetencia::Confirmada);

            $this->auditoria->registrar('fatura.confirmada', $fatura, null, [
                'valor_centavos' => $fatura->valor_centavos,
                'vencimento' => $fatura->vencimento->toDateString(),
                'verificacoes_com_falha' => array_column(array_filter($fatura->validacoes_json ?? [], fn ($v) => ! $v['ok']), 'regra'),
            ]);

            return $fatura;
        });
    }

    /**
     * Conta do condomínio digitada no painel (valor e consumo, sem PDF). O síndico é quem digita,
     * então ela já nasce confirmada.
     */
    public function lancarManual(Concessionaria $concessionaria, Carbon $mes, int $valorCentavos, ?string $consumo, User $autor): Fatura
    {
        if ($valorCentavos <= 0) {
            throw new RegraNegocio('Informe o valor da conta.');
        }

        $medidor = Medidor::query()->where('dono_tipo', 'condominio')->where('concessionaria', $concessionaria)
            ->where('ativo', true)->orderBy('id')->first();

        // A internet não tem medidor nem PDF: o "medidor" é só o contrato do prédio, criado no primeiro lançamento.
        if (! $medidor && ! $concessionaria->temLeitura()) {
            $medidor = Medidor::query()->firstOrCreate(
                ['concessionaria' => $concessionaria, 'identificador' => 'condominio'],
                ['dono_tipo' => 'condominio', 'descricao' => 'Internet do condomínio', 'ativo' => true],
            );
        }

        if (! $medidor) {
            throw new RegraNegocio('Cadastre o medidor da '.$concessionaria->rotulo().' do condomínio em Medidores.');
        }

        if (! $concessionaria->temLeitura()) {
            $consumo = null;
        }

        return DB::transaction(function () use ($medidor, $concessionaria, $mes, $valorCentavos, $consumo, $autor) {
            $competencia = $this->competencias->garantir($medidor, $mes);

            if (Fatura::query()->where('competencia_id', $competencia->id)->where('status', StatusFatura::Confirmada)->exists()) {
                throw new RegraNegocio('A conta da '.$concessionaria->rotulo().' de '.Mes::nome($competencia->mes_ref).' já foi lançada.');
            }

            $fatura = Fatura::query()->create([
                'competencia_id' => $competencia->id,
                'medidor_id' => $medidor->id,
                'origem' => 'manual',
                'valor_centavos' => $valorCentavos,
                'consumo' => filled($consumo) ? $consumo : null,
                'unidade_medida' => $concessionaria->unidadeMedida(),
                'status' => StatusFatura::Confirmada,
                'confirmada_por' => $autor->id,
                'confirmada_em' => now(),
            ]);

            $competencia->avancarPara(StatusCompetencia::Confirmada);

            $this->auditoria->registrar('fatura.lancada', $fatura, null, [
                'medidor' => $medidor->rotulo(),
                'competencia' => $competencia->mes_ref->format('Y-m'),
                'valor_centavos' => $valorCentavos,
                'consumo' => $fatura->consumo,
            ]);

            return $fatura->setRelation('medidor', $medidor)->setRelation('competencia', $competencia);
        });
    }

    public function rejeitar(Fatura $fatura, string $motivo, User $autor): Fatura
    {
        $this->garantirEditavel($fatura);

        if (trim($motivo) === '') {
            throw new RegraNegocio('Informe o motivo da rejeição.');
        }

        $fatura->update(['status' => StatusFatura::Rejeitada, 'motivo_revisao' => $motivo]);
        $this->auditoria->registrar('fatura.rejeitada', $fatura, null, ['motivo' => $motivo]);

        return $fatura;
    }

    /**
     * Conta individual: a unidade revisa e aceita a fatura que caiu em revisão.
     */
    public function aceitarIndividual(Fatura $fatura, User $autor): Fatura
    {
        if ($fatura->isCondominio() || $fatura->status !== StatusFatura::PendenteRevisao) {
            throw new RegraNegocio('Esta fatura não está aguardando revisão.');
        }

        if ($fatura->valor_centavos === null || $fatura->vencimento === null) {
            throw new RegraNegocio('Preencha o valor e o vencimento antes de aceitar.');
        }

        $fatura->update(['status' => StatusFatura::Processada]);
        $fatura->competencia->avancarPara(StatusCompetencia::Confirmada);
        $this->auditoria->registrar('fatura.aceita', $fatura, null, ['valor_centavos' => $fatura->valor_centavos]);

        return $fatura;
    }

    /**
     * Conta individual: a unidade informa que pagou.
     */
    public function marcarPaga(Fatura $fatura, Carbon $data): Fatura
    {
        if ($fatura->isCondominio()) {
            throw new RegraNegocio('O pagamento da conta do condomínio é lançado no caixa.');
        }

        $fatura->update(['paga_em' => $data->toDateString()]);
        $fatura->competencia->avancarPara(StatusCompetencia::Quitada);
        $this->auditoria->registrar('fatura.paga', $fatura, null, ['paga_em' => $data->toDateString()]);

        return $fatura;
    }

    /**
     * Pagamento da conta do condomínio à concessionária: saída no caixa, com comprovante.
     */
    public function pagarCondominio(Fatura $fatura, Carbon $data, UploadedFile $comprovante, User $autor): LancamentoCaixa
    {
        if (! $fatura->isCondominio() || $fatura->status !== StatusFatura::Confirmada) {
            throw new RegraNegocio('Só fatura do condomínio confirmada pode ser paga pelo caixa.');
        }

        if (LancamentoCaixa::query()->where('origem_tipo', 'fatura')->where('origem_id', $fatura->id)->whereDoesntHave('estornadoPor')->exists()) {
            throw new RegraNegocio('O pagamento desta fatura já foi lançado.');
        }

        return DB::transaction(function () use ($fatura, $data, $comprovante, $autor) {
            $concessionaria = $fatura->medidor->concessionaria;
            $lancamento = $this->caixa->lancar(
                ContaCaixa::corrente(),
                $data,
                'saida',
                (int) $fatura->valor_centavos,
                $concessionaria->categoriaPagamento(),
                'Pagamento '.$concessionaria->rotulo().' · '.Mes::nome($fatura->competencia->mes_ref).' · fatura de '.Mes::nome($fatura->competencia->mes_ref),
                $autor,
                $comprovante,
                'fatura',
                $fatura->id,
            );

            $fatura->update(['paga_em' => $data->toDateString()]);
            $fatura->competencia->avancarPara(StatusCompetencia::Quitada);

            return $lancamento;
        });
    }

    /**
     * LGPD: a unidade apaga o próprio histórico individual. As contas do condomínio permanecem.
     */
    public function excluirHistoricoIndividual(Unidade $unidade): int
    {
        return DB::transaction(function () use ($unidade) {
            $faturas = Fatura::query()->daUnidade($unidade->id)->with('anexo')->get();

            foreach ($faturas as $fatura) {
                $anexo = $fatura->anexo;
                $fatura->delete();

                if ($anexo && ! Fatura::query()->where('anexo_id', $anexo->id)->exists()) {
                    $this->armazenamento->disco()->delete($anexo->s3_key);
                    $anexo->delete();
                }
            }

            $unidade->medidores()->each(fn (Medidor $m) => $m->competencias()->whereDoesntHave('faturas')->delete());

            $this->auditoria->registrar('unidade.historico_excluido', $unidade, null, ['faturas' => $faturas->count()]);

            return $faturas->count();
        });
    }

    private function aplicarValidacao(Fatura $fatura): void
    {
        $validacoes = $this->validador->validar($fatura);
        $motivo = ValidadorFatura::motivo($validacoes);
        $fatura->validacoes_json = $validacoes;
        $fatura->motivo_revisao = $motivo;

        if (in_array($fatura->status, [StatusFatura::Confirmada, StatusFatura::Rejeitada], true)) {
            return;
        }

        $fatura->status = match (true) {
            $motivo !== null => StatusFatura::PendenteRevisao,
            $fatura->medidor->isCondominio() => StatusFatura::PendenteConfirmacao,
            default => StatusFatura::Processada,
        };
    }

    private function garantirEditavel(Fatura $fatura): void
    {
        if ($fatura->status === StatusFatura::Rejeitada) {
            throw new RegraNegocio('Fatura rejeitada não pode ser alterada.');
        }

        if ($fatura->rateios()->exists()) {
            throw new RegraNegocio('Esta fatura já entrou em um rateio. Correção agora só por ajuste no mês seguinte.');
        }

        if ($fatura->status === StatusFatura::Confirmada) {
            throw new RegraNegocio('Fatura confirmada não pode ser alterada.');
        }
    }

    private function avisarChegada(Fatura $fatura): void
    {
        $medidor = $fatura->medidor;
        $servico = mb_strtolower($medidor->concessionaria->servico());
        $valor = $fatura->valor_centavos !== null ? Dinheiro::formatar($fatura->valor_centavos) : 'valor a revisar';
        $vence = $fatura->vencimento ? ' · vence '.$fatura->vencimento->format('d/m') : '';

        if ($medidor->isCondominio()) {
            $this->notificador->admin(
                'fatura_condominio',
                'Revisar fatura '.$medidor->concessionaria->rotulo().' do condomínio',
                'Chegou '.($fatura->origem === 'email' ? 'por e-mail' : 'por upload').' · '.$valor,
                '/gestao/faturas/'.$fatura->id,
                'fatura:'.$fatura->id,
                ['concessionaria' => $medidor->concessionaria->value],
            );

            return;
        }

        $this->notificador->unidade(
            (int) $medidor->unidade_id,
            'fatura_individual',
            'Sua fatura de '.$servico.' chegou',
            $medidor->concessionaria->rotulo().' · '.$valor.$vence,
            '/minhas-contas',
            'fatura:'.$fatura->id,
            ['concessionaria' => $medidor->concessionaria->value],
        );

        $variacao = $fatura->consumo !== null ? $this->validador->variacaoConsumo($fatura) : null;
        $limite = $this->configuracoes->int('limite_anomalia_percentual');

        if ($variacao !== null && $variacao > $limite) {
            $this->notificador->unidade(
                (int) $medidor->unidade_id,
                'anomalia_consumo',
                (float) $fatura->consumo.' '.$fatura->unidade_medida.' — '.$variacao.'% acima da sua média',
                $medidor->concessionaria->servico() === 'Água'
                    ? 'Vale verificar torneiras e descarga: pode ser vazamento.'
                    : 'Vale conferir aparelhos ligados e o relógio do medidor.',
                '/minhas-contas',
                'anomalia:'.$fatura->id,
                ['concessionaria' => $medidor->concessionaria->value],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function serializar(array $valores): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $valores);
    }
}
