<?php

namespace App\Enums;

/**
 * prevista → leitura_realizada → fatura_recebida → confirmada → rateada → quitada.
 * Medidor individual termina em confirmada/quitada.
 */
enum StatusCompetencia: string
{
    case Prevista = 'prevista';
    case LeituraRealizada = 'leitura_realizada';
    case FaturaRecebida = 'fatura_recebida';
    case Confirmada = 'confirmada';
    case Rateada = 'rateada';
    case Quitada = 'quitada';

    public function ordem(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Prevista => 'Prevista',
            self::LeituraRealizada => 'Leitura feita',
            self::FaturaRecebida => 'Fatura recebida',
            self::Confirmada => 'Confirmada',
            self::Rateada => 'Rateada',
            self::Quitada => 'Quitada',
        };
    }
}
