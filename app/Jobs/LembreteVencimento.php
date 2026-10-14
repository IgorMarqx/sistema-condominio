<?php

namespace App\Jobs;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Services\Notificador;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Diário, 09:00. Cobrança vencendo em 3 dias ou vencida → lembrete à unidade (uma vez cada).
 */
class LembreteVencimento implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(Notificador $notificador): void
    {
        Cobranca::query()
            ->where('status', StatusCobranca::Aberta)
            ->whereDate('vencimento', '<=', today()->addDays(3))
            ->each(function (Cobranca $cobranca) use ($notificador) {
                $vencida = $cobranca->vencimento->lt(today());
                $mes = Mes::nome($cobranca->mes_ref);

                $notificador->unidade(
                    $cobranca->unidade_id,
                    $vencida ? 'cobranca_vencida' : 'cobranca_vencendo',
                    $vencida ? 'Cobrança de '.$mes.' vencida' : 'Cobrança de '.$mes.' vence '.$cobranca->vencimento->format('d/m'),
                    Dinheiro::formatar($cobranca->valor_centavos).' · pague com Pix e envie o comprovante.',
                    '/cobranca',
                    ($vencida ? 'vencida:' : 'vencendo:').$cobranca->id,
                );
            });
    }
}
