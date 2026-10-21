<?php

namespace App\Services;

use App\Exceptions\RegraNegocio;
use App\Support\ContatoRecuperacao;
use Illuminate\Support\Facades\Http;

class WhatsappService
{
    public function enviarTexto(string $telefone, string $mensagem): void
    {
        $celular = ContatoRecuperacao::celular($telefone);

        if ($celular === null || ! config('services.gzappy.api_key')) {
            throw new RegraNegocio('O envio por WhatsApp está indisponível. Tente por e-mail ou procure o síndico.');
        }

        Http::acceptJson()
            ->withToken((string) config('services.gzappy.api_key'))
            ->timeout((int) config('services.gzappy.timeout'))
            ->connectTimeout((int) config('services.gzappy.connect_timeout'))
            ->post(rtrim((string) config('services.gzappy.url'), '/').'/message/send-text', [
                'phone' => ['55'.$celular],
                'message' => $mensagem,
            ])->throw();
    }
}
