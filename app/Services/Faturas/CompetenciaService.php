<?php

namespace App\Services\Faturas;

use App\Enums\StatusCompetencia;
use App\Models\Competencia;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Support\Mes;
use Illuminate\Support\Carbon;

/**
 * Uma competência por medidor e mês. A leitura prevista vem da "próxima leitura" impressa na
 * fatura anterior; sem ela, última leitura + 30 dias. O síndico pode ajustar à mão.
 */
class CompetenciaService
{
    public function garantir(Medidor $medidor, Carbon $mes): Competencia
    {
        $mes = Mes::ref($mes);

        $competencia = Competencia::query()->firstOrCreate(
            ['medidor_id' => $medidor->id, 'mes_ref' => $mes->toDateString()],
            ['status' => StatusCompetencia::Prevista],
        );

        if ($competencia->leitura_prevista === null) {
            [$data, $origem] = $this->previsao($medidor, $mes);

            if ($data) {
                $competencia->update(['leitura_prevista' => $data, 'leitura_prevista_origem' => $origem]);
            }
        }

        return $competencia;
    }

    /**
     * @return array{0: Carbon|null, 1: string|null}
     */
    public function previsao(Medidor $medidor, Carbon $mes): array
    {
        $anterior = Fatura::query()
            ->where('medidor_id', $medidor->id)
            ->where('status', '!=', 'rejeitada')
            ->whereHas('competencia', fn ($q) => $q->where('mes_ref', '<', $mes->toDateString()))
            ->orderByDesc(Competencia::query()->select('mes_ref')->whereColumn('competencias.id', 'faturas.competencia_id'))
            ->first();

        if ($anterior?->proxima_leitura && $anterior->proxima_leitura->isSameMonth($mes)) {
            return [$anterior->proxima_leitura, 'fatura'];
        }

        if ($anterior?->leitura_atual) {
            $estimada = $anterior->leitura_atual->copy()->addDays(30);

            // A estimativa só vale se cair no mês da competência; senão a conta anterior é velha demais.
            if ($estimada->isSameMonth($mes)) {
                return [$estimada, 'estimada'];
            }

            $ultimaLeitura = $anterior->leitura_atual;
            $meses = $ultimaLeitura->copy()->startOfMonth()->diffInMonths($mes);

            return [$ultimaLeitura->copy()->addMonthsNoOverflow((int) $meses), 'estimada'];
        }

        return [null, null];
    }

    /**
     * Fatura recebida: grava a leitura feita e já prevê a do mês seguinte.
     */
    public function aposFatura(Fatura $fatura): void
    {
        $competencia = $fatura->competencia;

        if ($fatura->leitura_atual && $competencia->leitura_realizada === null) {
            $competencia->update(['leitura_realizada' => $fatura->leitura_atual]);
        }

        $competencia->avancarPara(StatusCompetencia::FaturaRecebida);

        if ($fatura->proxima_leitura) {
            $seguinte = $this->garantir($fatura->medidor, $competencia->mes_ref->copy()->addMonthNoOverflow());

            if ($seguinte->leitura_prevista_origem !== 'manual') {
                $seguinte->update(['leitura_prevista' => $fatura->proxima_leitura, 'leitura_prevista_origem' => 'fatura']);
            }
        }
    }
}
