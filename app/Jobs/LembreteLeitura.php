<?php

namespace App\Jobs;

use App\Models\Competencia;
use App\Services\Notificador;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Diário, 08:00. No dia da leitura do medidor do condomínio, lembra o síndico de conferir o
 * relógio e fotografar. A chave do aviso (competência) impede repetir no mesmo dia.
 */
class LembreteLeitura implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(Notificador $notificador): void
    {
        Competencia::query()
            ->whereDate('leitura_prevista', today())
            ->whereHas('medidor', fn ($q) => $q->where('dono_tipo', 'condominio')->where('ativo', true))
            ->with('medidor')
            ->each(function (Competencia $competencia) use ($notificador) {
                $medidor = $competencia->medidor;

                $notificador->admin(
                    'lembrete_leitura',
                    'Hoje é dia de leitura: '.mb_strtolower($medidor->concessionaria->servico()).' do condomínio',
                    'Confira o relógio ('.$medidor->identificador.') e tire uma foto para comparar com a fatura.',
                    '/gestao/leituras',
                    'leitura:'.$competencia->id,
                );
            });
    }
}
