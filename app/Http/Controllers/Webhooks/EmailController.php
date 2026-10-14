<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Ingestao\RecebedorEmail;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Entradas de e-mail:
 *  - SES → regra de recebimento (grava o .eml no S3) → SNS → HTTPS aqui (assinatura conferida);
 *  - webhook bruto (Mailgun "store and forward", Cloudflare Email Worker...) com token.
 */
class EmailController extends Controller
{
    public function sns(Request $request, RecebedorEmail $recebedor): JsonResponse
    {
        try {
            $mensagem = Message::fromJsonString($request->getContent());
            (new MessageValidator)->validate($mensagem);
        } catch (Throwable $e) {
            Log::warning('SNS: mensagem inválida.', ['erro' => $e->getMessage()]);

            return response()->json(['erro' => 'assinatura inválida'], 403);
        }

        $topico = (string) config('condominio.sns_topic_arn');

        if ($topico === '' || $mensagem['TopicArn'] !== $topico) {
            return response()->json(['erro' => 'tópico não aceito'], 403);
        }

        if ($mensagem['Type'] === 'SubscriptionConfirmation') {
            Http::timeout(10)->get($mensagem['SubscribeURL']);

            return response()->json(['ok' => true]);
        }

        if ($mensagem['Type'] !== 'Notification') {
            return response()->json(['ok' => true]);
        }

        $notificacao = json_decode((string) $mensagem['Message'], true) ?: [];
        $recibo = $notificacao['receipt'] ?? [];
        $acao = $recibo['action'] ?? [];

        // Ação S3 da regra do SES: o .eml bruto está no bucket. (Ação SNS com conteúdo vem em "content".)
        if (($acao['type'] ?? null) === 'S3') {
            $chave = (string) ($acao['objectKey'] ?? '');
            $bruto = (string) Storage::disk(config('condominio.ses_bucket_disco'))->get($chave);
        } else {
            $bruto = (string) ($notificacao['content'] ?? '');
            $chave = null;
        }

        if ($bruto === '') {
            return response()->json(['erro' => 'e-mail vazio'], 422);
        }

        $recebedor->receber(
            $bruto,
            array_values((array) ($recibo['recipients'] ?? [])),
            strtolower((string) ($recibo['spfVerdict']['status'] ?? '')) ?: null,
            strtolower((string) ($recibo['dkimVerdict']['status'] ?? '')) ?: null,
        );

        return response()->json(['ok' => true]);
    }

    public function bruto(Request $request, string $token, RecebedorEmail $recebedor): JsonResponse
    {
        $esperado = (string) config('condominio.webhook_email_token');

        if ($esperado === '' || ! hash_equals($esperado, $token)) {
            return response()->json(['erro' => 'token inválido'], 403);
        }

        // Mailgun manda o MIME em "body-mime"; outros, o corpo cru da requisição.
        $bruto = (string) ($request->input('body-mime') ?: $request->file('message')?->get() ?: $request->getContent());

        if ($bruto === '' || strlen($bruto) > 30 * 1024 * 1024) {
            return response()->json(['erro' => 'e-mail vazio ou grande demais'], 422);
        }

        $destinatarios = array_values(array_filter(array_map('trim', explode(',', (string) ($request->input('recipient') ?: $request->header('X-Envelope-To', ''))))));

        $email = $recebedor->receber($bruto, $destinatarios);

        return response()->json(['ok' => true, 'aceito' => $email !== null]);
    }
}
