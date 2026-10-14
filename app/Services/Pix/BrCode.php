<?php

namespace App\Services\Pix;

use App\Exceptions\RegraNegocio;
use Illuminate\Support\Str;

/**
 * Pix copia-e-cola (BR Code estático, padrão EMV do Banco Central) com valor e identificador.
 */
class BrCode
{
    public static function gerar(string $chave, string $recebedor, string $cidade, int $valorCentavos, ?string $txid = null, ?string $descricao = null): string
    {
        $chave = trim($chave);

        if ($chave === '') {
            throw new RegraNegocio('Cadastre a chave Pix do condomínio nas configurações.');
        }

        $conta = self::campo('00', 'br.gov.bcb.pix').self::campo('01', $chave);

        if ($descricao) {
            $conta .= self::campo('02', Str::limit(self::limpar($descricao), 40, ''));
        }

        $txid = $txid ? substr(preg_replace('/[^A-Za-z0-9]/', '', $txid) ?? '', 0, 25) : '***';

        $payload = self::campo('00', '01')
            .self::campo('26', $conta)
            .self::campo('52', '0000')
            .self::campo('53', '986')
            .self::campo('54', number_format($valorCentavos / 100, 2, '.', ''))
            .self::campo('58', 'BR')
            .self::campo('59', Str::limit(self::limpar($recebedor), 25, ''))
            .self::campo('60', Str::limit(self::limpar($cidade), 15, ''))
            .self::campo('62', self::campo('05', $txid !== '' ? $txid : '***'))
            .'6304';

        return $payload.self::crc16($payload);
    }

    private static function campo(string $id, string $valor): string
    {
        return $id.str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT).$valor;
    }

    private static function limpar(string $texto): string
    {
        return strtoupper(trim(preg_replace('/[^A-Za-z0-9 .\-]/', '', Str::ascii($texto)) ?? ''));
    }

    /**
     * CRC16-CCITT (polinômio 0x1021, valor inicial 0xFFFF).
     */
    public static function crc16(string $dados): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $n = strlen($dados); $i < $n; $i++) {
            $crc ^= ord($dados[$i]) << 8;

            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
