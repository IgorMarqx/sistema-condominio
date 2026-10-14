<?php

namespace App\Services\Cobrancas;

use App\Enums\CategoriaLancamento;
use App\Enums\StatusCobranca;
use App\Exceptions\RegraNegocio;
use App\Models\Cobranca;
use App\Models\ContaCaixa;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Models\Pagamento;
use App\Models\User;
use App\Services\Arquivos\Armazenamento;
use App\Services\Auditoria\Auditoria;
use App\Services\Caixa\CaixaService;
use App\Services\Configuracoes;
use App\Services\Notificador;
use App\Services\Pix\BrCode;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Unidade paga por Pix e envia o comprovante; o síndico confere no extrato do banco e confirma,
 * o que gera a entrada no caixa.
 */
class PagamentoService
{
    public function __construct(
        private readonly Armazenamento $armazenamento,
        private readonly Auditoria $auditoria,
        private readonly CaixaService $caixa,
        private readonly Notificador $notificador,
        private readonly Configuracoes $configuracoes,
    ) {}

    public function pixCopiaCola(Cobranca $cobranca): ?string
    {
        $chave = $this->configuracoes->string('pix_chave');

        if (! $chave) {
            return null;
        }

        return BrCode::gerar(
            $chave,
            (string) ($this->configuracoes->string('pix_recebedor') ?: $this->configuracoes->string('nome_edificio')),
            (string) $this->configuracoes->string('pix_cidade'),
            $cobranca->valor_centavos,
            $cobranca->pix_txid,
        );
    }

    public function enviarComprovante(Cobranca $cobranca, UploadedFile $arquivo, Carbon $pagoEm, User $autor, ?string $nomeInformado, ?int $valorCentavos = null): Pagamento
    {
        if (in_array($cobranca->status, [StatusCobranca::Paga, StatusCobranca::Cancelada], true)) {
            throw new RegraNegocio('Esta cobrança já está '.($cobranca->status === StatusCobranca::Paga ? 'paga' : 'cancelada').'.');
        }

        if ($pagoEm->isFuture()) {
            throw new RegraNegocio('A data do pagamento não pode ser no futuro.');
        }

        $guardado = $this->armazenamento->guardarUpload($arquivo, 'comprovantes');

        return DB::transaction(function () use ($cobranca, $arquivo, $guardado, $pagoEm, $autor, $nomeInformado, $valorCentavos) {
            $pagamento = Pagamento::query()->create([
                'cobranca_id' => $cobranca->id,
                'valor_centavos' => $valorCentavos ?? $this->emAberto($cobranca),
                'pago_em' => $pagoEm->toDateString(),
                'comprovante_s3' => $guardado['caminho'],
                'sha256' => $guardado['sha256'],
                'comprovante_nome' => $this->armazenamento->nomeSeguro($arquivo->getClientOriginalName()),
                'status' => 'aguardando',
                'enviado_por' => $autor->id,
                'nome_informado' => $nomeInformado,
            ]);

            $cobranca->update(['status' => StatusCobranca::AguardandoConfirmacao]);

            $this->auditoria->registrar('pagamento.comprovante_enviado', $pagamento, null, [
                'cobranca_id' => $cobranca->id,
                'unidade' => $cobranca->unidade->numero,
                'valor_centavos' => $pagamento->valor_centavos,
                'pago_em' => $pagoEm->toDateString(),
                'sha256' => $guardado['sha256'],
            ]);

            $this->notificador->admin(
                'comprovante_enviado',
                'Comprovante do apto '.$cobranca->unidade->numero,
                Dinheiro::formatar($pagamento->valor_centavos).' · pago em '.$pagoEm->format('d/m'),
                '/gestao/cobrancas?mes='.$cobranca->mes_ref->format('Y-m'),
                'comprovante:'.$pagamento->id,
            );

            return $pagamento;
        });
    }

    /**
     * Síndico registra um recebimento direto (ex.: transferência sem comprovante da unidade),
     * anexando ele mesmo o comprovante. Já entra confirmado.
     */
    public function registrarRecebimento(Cobranca $cobranca, UploadedFile $arquivo, Carbon $pagoEm, int $valorCentavos, User $admin): Pagamento
    {
        $pagamento = $this->enviarComprovante($cobranca, $arquivo, $pagoEm, $admin, null, $valorCentavos);

        return $this->confirmar($pagamento, $admin);
    }

    public function confirmar(Pagamento $pagamento, User $admin): Pagamento
    {
        if ($pagamento->status !== 'aguardando') {
            throw new RegraNegocio('Este pagamento já foi '.($pagamento->status === 'confirmado' ? 'confirmado' : 'recusado').'.');
        }

        return DB::transaction(function () use ($pagamento, $admin) {
            $pagamento = Pagamento::query()->whereKey($pagamento->id)->lockForUpdate()->firstOrFail();
            $cobranca = $pagamento->cobranca;

            $lancamento = $this->caixa->lancar(
                ContaCaixa::corrente(),
                $this->dataDoLancamento($pagamento->pago_em),
                'entrada',
                $pagamento->valor_centavos,
                CategoriaLancamento::Cobranca,
                'Cobrança · apto '.$cobranca->unidade->numero.' · '.Mes::nome($cobranca->mes_ref),
                $admin,
                ['caminho' => $pagamento->comprovante_s3, 'sha256' => $pagamento->sha256],
                'pagamento',
                $pagamento->id,
            );

            $pagamento->update([
                'status' => 'confirmado',
                'confirmado_por' => $admin->id,
                'confirmado_em' => now(),
            ]);

            $quitada = $this->totalConfirmado($cobranca) >= $cobranca->valor_centavos;
            $cobranca->update(['status' => $quitada ? StatusCobranca::Paga : StatusCobranca::Aberta]);

            $this->auditoria->registrar('pagamento.confirmado', $pagamento, null, [
                'cobranca_id' => $cobranca->id,
                'unidade' => $cobranca->unidade->numero,
                'valor_centavos' => $pagamento->valor_centavos,
                'lancamento_id' => $lancamento->id,
                'cobranca_quitada' => $quitada,
            ]);

            $this->notificador->unidade(
                $cobranca->unidade_id,
                'pagamento_confirmado',
                $quitada ? 'Pagamento de '.Mes::nome($cobranca->mes_ref).' confirmado' : 'Pagamento parcial confirmado',
                Dinheiro::formatar($pagamento->valor_centavos).' entrou no caixa do condomínio.',
                '/cobranca',
                'pagamento-confirmado:'.$pagamento->id,
            );

            return $pagamento;
        });
    }

    public function recusar(Pagamento $pagamento, string $motivo, User $admin): Pagamento
    {
        if ($pagamento->status !== 'aguardando') {
            throw new RegraNegocio('Este pagamento não está aguardando confirmação.');
        }

        if (trim($motivo) === '') {
            throw new RegraNegocio('Informe o motivo da recusa.');
        }

        return DB::transaction(function () use ($pagamento, $motivo, $admin) {
            $pagamento->update(['status' => 'recusado', 'motivo_recusa' => $motivo, 'confirmado_por' => $admin->id, 'confirmado_em' => now()]);
            $cobranca = $pagamento->cobranca;

            $aguardando = $cobranca->pagamentos()->where('status', 'aguardando')->exists();
            $cobranca->update(['status' => $aguardando ? StatusCobranca::AguardandoConfirmacao : StatusCobranca::Aberta]);

            $this->auditoria->registrar('pagamento.recusado', $pagamento, null, ['motivo' => $motivo]);

            $this->notificador->unidade(
                $cobranca->unidade_id,
                'pagamento_recusado',
                'Comprovante não confirmado',
                $motivo,
                '/cobranca',
                'pagamento-recusado:'.$pagamento->id,
            );

            return $pagamento;
        });
    }

    public function totalConfirmado(Cobranca $cobranca): int
    {
        return (int) $cobranca->pagamentos()->where('status', 'confirmado')->sum('valor_centavos');
    }

    public function emAberto(Cobranca $cobranca): int
    {
        return max(0, $cobranca->valor_centavos - $this->totalConfirmado($cobranca));
    }

    /**
     * Pagamento feito num mês já fechado entra no caixa no primeiro dia aberto.
     */
    private function dataDoLancamento(Carbon $pagoEm): Carbon
    {
        $travado = Fechamento::travadoAte();

        return $travado && $pagoEm->lte($travado) ? $travado->copy()->addDay() : $pagoEm;
    }

    /**
     * Estorno do lançamento de um pagamento confirmado por engano: a cobrança volta a ficar aberta.
     */
    public function aposEstorno(LancamentoCaixa $original): void
    {
        if ($original->origem_tipo !== 'pagamento') {
            return;
        }

        $pagamento = Pagamento::query()->find($original->origem_id);

        if (! $pagamento) {
            return;
        }

        $pagamento->update(['status' => 'recusado', 'motivo_recusa' => 'Lançamento estornado no caixa.']);
        $cobranca = $pagamento->cobranca;
        $cobranca->update(['status' => $this->totalConfirmado($cobranca) >= $cobranca->valor_centavos ? StatusCobranca::Paga : StatusCobranca::Aberta]);
    }
}
