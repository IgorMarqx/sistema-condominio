<?php

namespace App\Enums;

enum StatusFatura: string
{
    case PendenteRevisao = 'pendente_revisao';
    case PendenteConfirmacao = 'pendente_confirmacao';
    case Processada = 'processada';
    case Confirmada = 'confirmada';
    case Rejeitada = 'rejeitada';

    public function rotulo(): string
    {
        return match ($this) {
            self::PendenteRevisao => 'Revisar',
            self::PendenteConfirmacao => 'Conferir',
            self::Processada => 'Processada',
            self::Confirmada => 'Confirmada',
            self::Rejeitada => 'Rejeitada',
        };
    }
}
