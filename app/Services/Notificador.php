<?php

namespace App\Services;

use App\Mail\AvisoMail;
use App\Models\Notificacao;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos do sistema: ficam na tela (sino/lista) e, se a unidade cadastrou e-mail, vão por e-mail.
 * A chave única impede o mesmo aviso duas vezes quando o job diário roda de novo.
 */
class Notificador
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function unidade(int $unidadeId, string $tipo, string $titulo, ?string $mensagem = null, ?string $url = null, ?string $chave = null, array $payload = []): ?Notificacao
    {
        $notificacao = $this->criar('unidade', $unidadeId, $tipo, $titulo, $mensagem, $url, $chave, $payload);

        if ($notificacao) {
            $email = Unidade::query()->whereKey($unidadeId)->value('email_avisos');

            if ($email) {
                Mail::to($email)->queue(new AvisoMail($titulo, $mensagem, $url));
            }
        }

        return $notificacao;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function admin(string $tipo, string $titulo, ?string $mensagem = null, ?string $url = null, ?string $chave = null, array $payload = []): ?Notificacao
    {
        $notificacao = $this->criar('admin', null, $tipo, $titulo, $mensagem, $url, $chave, $payload);

        if ($notificacao) {
            User::query()->where('tipo', 'admin')->where('ativo', true)->whereNotNull('email')->pluck('email')
                ->each(fn (string $email) => Mail::to($email)->queue(new AvisoMail($titulo, $mensagem, $url)));
        }

        return $notificacao;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function criar(string $destino, ?int $unidadeId, string $tipo, string $titulo, ?string $mensagem, ?string $url, ?string $chave, array $payload): ?Notificacao
    {
        if ($chave && Notificacao::query()->where('chave_unica', $chave)->exists()) {
            return null;
        }

        try {
            return Notificacao::query()->create([
                'destino' => $destino,
                'unidade_id' => $unidadeId,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'url' => $url,
                'payload' => $payload ?: null,
                'chave_unica' => $chave,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
