<?php

namespace App\Support;

/**
 * Senha de 16 caracteres para entregar em papel: sem caracteres ambíguos (0/O, 1/l/I).
 */
final class SenhaAleatoria
{
    private const ALFABETO = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function gerar(int $tamanho = 16): string
    {
        $senha = '';
        $max = strlen(self::ALFABETO) - 1;

        do {
            $senha = '';

            for ($i = 0; $i < $tamanho; $i++) {
                $senha .= self::ALFABETO[random_int(0, $max)];
            }
        } while (! preg_match('/\d/', $senha) || ! preg_match('/[a-z]/', $senha) || ! preg_match('/[A-Z]/', $senha));

        return $senha;
    }
}
