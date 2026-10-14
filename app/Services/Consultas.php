<?php

namespace App\Services;

use App\Enums\Concessionaria;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Competencia;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Services\Caixa\CaixaService;
use App\Support\Mes;

/**
 * Leituras usadas em mais de uma tela (início, contas do condomínio, minhas contas, painel).
 */
class Consultas
{
    /**
     * Consumo dos últimos meses do medidor, do mais antigo para o mais novo (gráfico de barras).
     *
     * @return list<array{mes: string, rotulo: string, consumo: float|null, valor: int|null, fatura_id: int|null}>
     */
    public function historicoConsumo(Medidor $medidor, int $meses = 6): array
    {
        $faturas = Fatura::query()
            ->where('medidor_id', $medidor->id)
            ->where('status', '!=', 'rejeitada')
            ->with('competencia')
            ->get()
            ->keyBy(fn (Fatura $f) => $f->competencia->mes_ref->format('Y-m'));

        $ultimo = $faturas->keys()->sort()->last();
        $fim = $ultimo ? Mes::ref($ultimo) : today()->startOfMonth();
        $serie = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = $fim->copy()->subMonthsNoOverflow($i);
            $fatura = $faturas->get($mes->format('Y-m'));
            $serie[] = [
                'mes' => $mes->format('Y-m'),
                'rotulo' => Mes::curto($mes),
                'consumo' => $fatura?->consumo !== null ? (float) $fatura->consumo : null,
                'valor' => $fatura?->valor_centavos,
                'fatura_id' => $fatura?->id,
            ];
        }

        return $serie;
    }

    /**
     * Próxima leitura prevista de cada medidor do condomínio (a partir de hoje).
     *
     * @return list<array{concessionaria: string, servico: string, data: string, origem: string|null}>
     */
    public function proximasLeiturasCondominio(): array
    {
        return array_values(Competencia::query()
            ->whereHas('medidor', fn ($q) => $q->where('dono_tipo', 'condominio')->where('ativo', true))
            ->whereDate('leitura_prevista', '>=', today())
            ->with('medidor')
            ->orderBy('leitura_prevista')
            ->get()
            ->unique('medidor_id')
            ->sortBy(fn (Competencia $c) => $c->medidor->concessionaria === Concessionaria::Energisa ? 0 : 1)
            ->map(fn (Competencia $c) => [
                'concessionaria' => $c->medidor->concessionaria->value,
                'servico' => $c->medidor->concessionaria->servico(),
                'data' => $c->leitura_prevista->toDateString(),
                'origem' => $c->leitura_prevista_origem,
            ])
            ->all());
    }

    /**
     * Previsão: saldo atual + cobranças em aberto − contas do condomínio ainda não pagas.
     *
     * @return array{saldo: int, a_receber: int, a_pagar: int, previsto: int}
     */
    public function previsaoSaldo(): array
    {
        $saldo = app(CaixaService::class)->saldo();

        $aReceber = (int) Cobranca::query()
            ->whereIn('status', [StatusCobranca::Aberta, StatusCobranca::AguardandoConfirmacao])
            ->get()
            ->sum(fn (Cobranca $c) => max(0, $c->valor_centavos - (int) $c->pagamentos()->where('status', 'confirmado')->sum('valor_centavos')));

        $aPagar = (int) Fatura::query()
            ->doCondominio()
            ->whereIn('status', ['confirmada', 'pendente_confirmacao', 'pendente_revisao'])
            ->whereNull('paga_em')
            ->sum('valor_centavos');

        return ['saldo' => $saldo, 'a_receber' => $aReceber, 'a_pagar' => $aPagar, 'previsto' => $saldo + $aReceber - $aPagar];
    }
}
