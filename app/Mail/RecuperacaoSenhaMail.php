<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RecuperacaoSenhaMail extends Mailable
{
    public function __construct(
        public string $link,
        public int $validade,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recuperar senha · '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.recuperacao-senha');
    }
}
