<?php

namespace App\Services\Caixa;

use App\Enums\CategoriaLancamento;
use App\Exceptions\RegraNegocio;
use App\Models\Cobranca;
use App\Models\ContaCaixa;
use App\Models\Fatura;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Models\Rateio;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Arquivos\Armazenamento;
use App\Services\Auditoria\Auditoria;
use App\Services\Configuracoes;
use App\Services\Notificador;
use App\Support\Dinheiro;
use App\Support\Mes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fechamento do mês: concilia o saldo calculado com o do extrato do banco, trava o mês para
 * novos lançamentos e gera a prestação de contas em PDF (com o hash da auditoria).
 */
class FechamentoService
{
    public function __construct(
        private readonly CaixaService $caixa,
        private readonly Auditoria $auditoria,
        private readonly Armazenamento $armazenamento,
        private readonly Configuracoes $configuracoes,
        private readonly Notificador $notificador,
    ) {}

    /**
     * Próximo mês que pode ser fechado: o seguinte ao último fechado, ou o do primeiro lançamento.
     */
    public function proximoMes(): ?Carbon
    {
        $ultimo = Fechamento::query()->orderByDesc('mes_ref')->value('mes_ref');

        if ($ultimo) {
            return Carbon::parse($ultimo)->addMonthNoOverflow()->startOfMonth();
        }

        $primeiro = LancamentoCaixa::query()->min('data');

        return $primeiro ? Mes::ref(Carbon::parse($primeiro)) : null;
    }

    /**
     * @return array{mes: string, contas: array<string, array{nome: string, calculado: int}>, total: int, movimento: array{entradas: int, saidas: int}, pode_fechar: bool, motivo: string|null}
     */
    public function previa(Carbon $mes): array
    {
        $mes = Mes::ref($mes);
        $fim = $mes->copy()->endOfMonth();
        $contas = [];

        foreach (ContaCaixa::query()->orderBy('id')->get() as $conta) {
            $contas[$conta->slug] = ['nome' => $conta->nome, 'calculado' => $this->caixa->saldo($conta, $fim)];
        }

        $motivo = null;
        $proximo = $this->proximoMes();

        if (Fechamento::query()->where('mes_ref', $mes)->exists()) {
            $motivo = Mes::rotulo($mes).' já está fechado.';
        } elseif ($proximo && ! $mes->equalTo($proximo)) {
            $motivo = 'Feche os meses em ordem: o próximo é '.Mes::rotulo($proximo).'.';
        } elseif ($fim->gte(today())) {
            $motivo = 'O mês só pode ser fechado depois que terminar.';
        }

        return [
            'mes' => $mes->format('Y-m'),
            'contas' => $contas,
            'total' => array_sum(array_column($contas, 'calculado')),
            'movimento' => $this->caixa->movimentoDoMes($mes),
            'pode_fechar' => $motivo === null,
            'motivo' => $motivo,
        ];
    }

    /**
     * @param  array<string, int>  $saldosBanco  saldo do extrato por slug de conta, em centavos
     */
    public function fechar(Carbon $mes, array $saldosBanco, ?string $observacao, User $autor): Fechamento
    {
        $previa = $this->previa($mes);

        if (! $previa['pode_fechar']) {
            throw new RegraNegocio((string) $previa['motivo']);
        }

        $contas = [];

        foreach ($previa['contas'] as $slug => $conta) {
            if (! array_key_exists($slug, $saldosBanco)) {
                throw new RegraNegocio('Informe o saldo do extrato da conta '.$conta['nome'].'.');
            }

            $contas[$slug] = [...$conta, 'banco' => (int) $saldosBanco[$slug]];
        }

        $banco = array_sum(array_column($contas, 'banco'));
        $diferenca = $banco - $previa['total'];

        if ($diferenca !== 0 && trim((string) $observacao) === '') {
            throw new RegraNegocio('O saldo do banco difere em '.Dinheiro::formatar($diferenca).'. Explique a diferença na observação para fechar.');
        }

        $mesRef = Mes::ref($mes);

        return DB::transaction(function () use ($mesRef, $previa, $contas, $banco, $diferenca, $observacao, $autor) {
            $fechamento = Fechamento::query()->create([
                'mes_ref' => $mesRef,
                'saldo_calculado_centavos' => $previa['total'],
                'saldo_banco_centavos' => $banco,
                'diferenca_centavos' => $diferenca,
                'contas_json' => $contas,
                'observacao' => $observacao,
                'fechado_por' => $autor->id,
                'fechado_em' => now(),
            ]);

            $registro = $this->auditoria->registrar('caixa.fechamento', $fechamento, null, [
                'mes' => $mesRef->format('Y-m'),
                'saldo_calculado' => $previa['total'],
                'saldo_banco' => $banco,
                'diferenca' => $diferenca,
            ]);

            $fechamento->hash_auditoria = $registro->hash;
            $pdf = $this->gerarPdf($fechamento);
            $arquivo = $this->armazenamento->guardarConteudo($pdf, 'prestacoes', 'pdf');

            $fechamento->update([
                'hash_auditoria' => $registro->hash,
                'pdf_s3' => $arquivo['caminho'],
                'pdf_sha256' => $arquivo['sha256'],
            ]);

            foreach (Unidade::query()->where('ativa', true)->pluck('id') as $unidadeId) {
                $this->notificador->unidade(
                    $unidadeId,
                    'prestacao_contas',
                    'Prestação de contas de '.Mes::nome($mesRef),
                    $diferenca === 0 ? 'Mês fechado · saldo conciliado.' : 'Mês fechado com diferença de '.Dinheiro::formatar($diferenca).'.',
                    '/caixa',
                    'prestacao:'.$fechamento->id.':'.$unidadeId,
                );
            }

            return $fechamento;
        });
    }

    /**
     * Prestação de contas do mês: faturas do condomínio, rateio, recebimentos, extrato e conciliação.
     */
    public function gerarPdf(Fechamento $fechamento): string
    {
        $mes = $fechamento->mes_ref;
        $inicio = $mes->copy()->startOfMonth()->toDateString();
        $fim = $mes->copy()->endOfMonth()->toDateString();

        $faturas = Fatura::query()->doCondominio()
            ->whereHas('competencia', fn ($q) => $q->where('mes_ref', $mes->toDateString()))
            ->where('status', '!=', 'rejeitada')
            ->with(['medidor', 'anexo'])
            ->get();

        $rateio = Rateio::query()->where('mes_ref', $mes->toDateString())->where('status', 'confirmado')->first();
        $cobrancas = $rateio
            ? Cobranca::query()->where('rateio_id', $rateio->id)->with(['unidade', 'pagamentos' => fn ($q) => $q->where('status', 'confirmado')])->get()->sortBy(fn ($c) => [$c->unidade->andar, $c->unidade->numero])
            : collect();

        $lancamentos = LancamentoCaixa::query()
            ->whereBetween('data', [$inicio, $fim])
            ->with(['conta', 'estornadoPor'])
            ->orderBy('data')->orderBy('id')
            ->get();

        $saldoInicial = $this->caixa->saldo(null, $mes->copy()->subDay());

        return Pdf::loadView('pdf.prestacao-contas', [
            'edificio' => $this->configuracoes->get('nome_edificio'),
            'endereco' => $this->configuracoes->get('endereco'),
            'mes' => Mes::rotulo($mes),
            'fechamento' => $fechamento,
            'faturas' => $faturas,
            'rateio' => $rateio,
            'cobrancas' => $cobrancas,
            'lancamentos' => $lancamentos,
            'saldoInicial' => $saldoInicial,
            'movimento' => $this->caixa->movimentoDoMes($mes),
            'categoriaTransferencia' => CategoriaLancamento::Transferencia,
        ])->setPaper('a4')->output();
    }
}
