<?php

namespace App\Jobs;

use App\Enums\StatusCompetencia;
use App\Models\Competencia;
use App\Services\Notificador;
use App\Support\Mes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Diário, 09:00. Leitura + 7 dias e nenhuma fatura: avisa que a fatura não chegou.
 */
class FaturaAtrasada implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(Notificador $notificador): void
    {
        Competencia::query()
            ->whereIn('status', [StatusCompetencia::Prevista, StatusCompetencia::LeituraRealizada])
            ->whereNotNull('leitura_prevista')
            ->whereDate('leitura_prevista', '<=', today()->subDays(7))
            ->whereDoesntHave('faturas', fn ($q) => $q->where('status', '!=', 'rejeitada'))
            ->whereHas('medidor', fn ($q) => $q->where('ativo', true))
            ->with('medidor')
            ->each(function (Competencia $competencia) use ($notificador) {
                $medidor = $competencia->medidor;
                $titulo = 'Fatura '.$medidor->concessionaria->rotulo().' de '.Mes::nome($competencia->mes_ref).' não chegou';
                $mensagem = 'A leitura era '.$competencia->leitura_prevista->format('d/m').'. Confira o e-mail cadastrado na concessionária ou envie o PDF.';
                $chave = 'atrasada:'.$competencia->id;

                if ($medidor->isCondominio()) {
                    $notificador->admin('fatura_atrasada', $titulo, $mensagem, '/gestao/leituras', $chave);
                } else {
                    $notificador->unidade((int) $medidor->unidade_id, 'fatura_atrasada', $titulo, $mensagem, '/receber-faturas', $chave);
                }
            });
    }
}
