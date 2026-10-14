<?php

namespace App\Mail;

use App\Services\Configuracoes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvisoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $titulo,
        public ?string $mensagem,
        public ?string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->titulo.' · '.app(Configuracoes::class)->get('nome_edificio'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.aviso', with: [
            'link' => $this->url ? url($this->url) : null,
        ]);
    }
}
