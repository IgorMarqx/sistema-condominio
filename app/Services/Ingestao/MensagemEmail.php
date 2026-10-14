<?php

namespace App\Services\Ingestao;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * O .eml bruto, lido com o parser MIME, com só o que a ingestão precisa.
 */
final class MensagemEmail
{
    private function __construct(private readonly IMessage $mensagem) {}

    public static function ler(string $bruto): self
    {
        return new self((new MailMimeParser)->parse($bruto, false));
    }

    public function remetente(): ?string
    {
        $header = $this->mensagem->getHeader('From');

        return $header instanceof AddressHeader ? strtolower((string) $header->getEmail()) : null;
    }

    public function assunto(): ?string
    {
        return $this->mensagem->getSubject();
    }

    public function messageId(): ?string
    {
        return $this->mensagem->getMessageId();
    }

    /**
     * Endereços que podem ser a nossa caixa: To/Cc e os cabeçalhos que o encaminhamento preserva.
     *
     * @return list<string>
     */
    public function destinatarios(): array
    {
        $enderecos = [];

        foreach (['To', 'Cc', 'Delivered-To', 'X-Original-To', 'X-Forwarded-To', 'Envelope-To'] as $nome) {
            foreach ($this->mensagem->getAllHeadersByName($nome) as $header) {
                if ($header instanceof AddressHeader) {
                    foreach ($header->getAddresses() as $endereco) {
                        $enderecos[] = strtolower((string) $endereco->getEmail());
                    }

                    continue;
                }

                if (preg_match_all('/[\w.+\-]+@[\w\-.]+/', (string) $header->getRawValue(), $m)) {
                    array_push($enderecos, ...array_map('strtolower', $m[0]));
                }
            }
        }

        return array_values(array_unique(array_filter($enderecos)));
    }

    public function texto(): string
    {
        $texto = $this->mensagem->getTextContent();

        if ($texto !== null && trim($texto) !== '') {
            return $texto;
        }

        $html = (string) $this->mensagem->getHtmlContent();

        return html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr)[^>]*>/i', "\n", $html) ?? $html));
    }

    /**
     * Links do corpo (texto e href do HTML). Só registrados, nunca seguidos.
     *
     * @return list<string>
     */
    public function links(): array
    {
        $conteudo = $this->texto().' '.(string) $this->mensagem->getHtmlContent();
        preg_match_all('/https?:\/\/[^\s"\'<>]+/i', $conteudo, $m);

        return array_values(array_unique(array_map(fn ($l) => rtrim(html_entity_decode($l), '.,;)'), $m[0])));
    }

    /**
     * Anexos PDF (tipo ou extensão). O resto é ignorado.
     *
     * @return list<array{nome: string, conteudo: string, tamanho: int}>
     */
    public function pdfs(): array
    {
        $pdfs = [];

        foreach ($this->mensagem->getAllAttachmentParts() as $parte) {
            $nome = (string) ($parte->getFilename() ?? 'fatura.pdf');
            $tipo = strtolower((string) $parte->getContentType());

            if ($tipo !== 'application/pdf' && ! str_ends_with(strtolower($nome), '.pdf')) {
                continue;
            }

            $conteudo = (string) $parte->getContent();
            $pdfs[] = ['nome' => $nome, 'conteudo' => $conteudo, 'tamanho' => strlen($conteudo)];
        }

        return $pdfs;
    }

    public function quantidadeAnexos(): int
    {
        return $this->mensagem->getAttachmentCount();
    }

    /**
     * Veredito SPF/DKIM do cabeçalho Authentication-Results do nosso servidor de entrada.
     *
     * @return array{spf: string|null, dkim: string|null}
     */
    public function autenticacao(): array
    {
        $header = $this->mensagem->getHeader('Authentication-Results');
        $valor = $header ? (string) $header->getRawValue() : '';

        $spf = preg_match('/\bspf=(\w+)/i', $valor, $m) ? strtolower($m[1]) : null;
        $dkim = preg_match('/\bdkim=(\w+)/i', $valor, $m) ? strtolower($m[1]) : null;

        return ['spf' => $spf, 'dkim' => $dkim];
    }
}
