<?php

namespace App\Services\Ingestao;

use App\Jobs\ProcessarEmailRecebido;
use App\Models\EmailRecebido;
use App\Services\Arquivos\Armazenamento;
use Illuminate\Support\Facades\Log;

/**
 * Porta de entrada de todo e-mail (SES/SNS, webhook bruto, comando). Grava o .eml, descobre
 * a caixa pelo destinatário e manda processar na fila "ingestao".
 */
class RecebedorEmail
{
    public function __construct(
        private readonly Armazenamento $armazenamento,
        private readonly CaixasEntradaService $caixas,
    ) {}

    /**
     * @param  list<string>  $destinatariosEnvelope  RCPT TO do servidor de entrada (vale mais que o To:)
     */
    public function receber(string $bruto, array $destinatariosEnvelope = [], ?string $spf = null, ?string $dkim = null, ?string $s3Existente = null): ?EmailRecebido
    {
        $mensagem = MensagemEmail::ler($bruto);
        $caixa = $this->caixas->localizar($destinatariosEnvelope) ?? $this->caixas->localizar($mensagem->destinatarios());

        if (! $caixa) {
            // Endereço desconhecido ou regenerado: descarta sem guardar o conteúdo.
            Log::info('E-mail descartado: destinatário desconhecido.', [
                'destinatarios' => $destinatariosEnvelope ?: $mensagem->destinatarios(),
                'remetente' => $mensagem->remetente(),
            ]);

            return null;
        }

        $autenticacao = $mensagem->autenticacao();
        $arquivo = $s3Existente
            ? ['caminho' => $s3Existente, 'tamanho' => strlen($bruto)]
            : $this->armazenamento->guardarConteudo($bruto, 'emails', 'eml');

        $email = EmailRecebido::query()->create([
            'caixa_entrada_id' => $caixa->id,
            'destinatario' => $caixa->endereco,
            'remetente' => $mensagem->remetente(),
            'assunto' => mb_substr((string) $mensagem->assunto(), 0, 500),
            'message_id' => $mensagem->messageId(),
            's3_eml' => $arquivo['caminho'],
            'tamanho' => $arquivo['tamanho'],
            'spf' => $spf ?? $autenticacao['spf'],
            'dkim' => $dkim ?? $autenticacao['dkim'],
            'status' => 'recebido',
            'recebido_em' => now(),
        ]);

        ProcessarEmailRecebido::dispatch($email->id);

        return $email;
    }
}
