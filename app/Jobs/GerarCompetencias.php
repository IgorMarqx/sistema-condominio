<?php

namespace App\Jobs;

use App\Models\Medidor;
use App\Services\Faturas\CompetenciaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Diário, 06:00. Garante a competência do mês para cada medidor ativo. Idempotente
 * (UNIQUE medidor + mês), então rodar duas vezes não cria nada a mais.
 */
class GerarCompetencias implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(CompetenciaService $competencias): void
    {
        $mes = today()->startOfMonth();

        Medidor::query()->where('ativo', true)->each(fn (Medidor $medidor) => $competencias->garantir($medidor, $mes));
    }
}
