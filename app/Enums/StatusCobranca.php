<?php

namespace App\Enums;

enum StatusCobranca: string
{
    case Aberta = 'aberta';
    case AguardandoConfirmacao = 'aguardando_confirmacao';
    case Paga = 'paga';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::AguardandoConfirmacao => 'Conferir',
            self::Paga => 'Paga',
            self::Cancelada => 'Cancelada',
        };
    }
}
