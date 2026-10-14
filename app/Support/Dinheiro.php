<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Valores monetários vivem em centavos inteiros. Aqui só se converte na borda.
 */
final class Dinheiro
{
    public static function formatar(int $centavos, bool $simbolo = true): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $texto = number_format(abs($centavos) / 100, 2, ',', '.');

        return $sinal.($simbolo ? 'R$ ' : '').$texto;
    }

    /**
     * Aceita "104,22", "1.104,22", "104.22", "R$ 104,22" e "10422" (já em centavos? não: reais).
     */
    public static function paraCentavos(string|int|float $valor): int
    {
        if (is_int($valor)) {
            return $valor * 100;
        }

        if (is_float($valor)) {
            return (int) round($valor * 100);
        }

        $texto = trim(str_replace(['R$', ' ', "\u{00A0}"], '', $valor));
        $negativo = str_starts_with($texto, '-');
        $texto = ltrim($texto, '-+');

        if ($texto === '' || ! preg_match('/^[\d.,]+$/', $texto)) {
            throw new InvalidArgumentException("Valor monetário inválido: {$valor}");
        }

        // A última vírgula ou ponto seguida de 1–2 dígitos é a casa decimal; o resto é milhar.
        if (preg_match('/^(.*)[.,](\d{1,2})$/', $texto, $m)) {
            $inteiro = preg_replace('/\D/', '', $m[1]);
            $centavos = (int) ($inteiro === '' ? '0' : $inteiro) * 100 + (int) str_pad($m[2], 2, '0');
        } else {
            $centavos = (int) preg_replace('/\D/', '', $texto) * 100;
        }

        return $negativo ? -$centavos : $centavos;
    }
}
