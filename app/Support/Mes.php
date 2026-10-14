<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class Mes
{
    private const NOMES = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    private const CURTOS = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    /**
     * "2026-10" ou uma data qualquer → primeiro dia do mês.
     */
    public static function ref(string|CarbonInterface|null $mes = null): Carbon
    {
        if ($mes === null) {
            return Carbon::today()->startOfMonth();
        }

        if ($mes instanceof CarbonInterface) {
            return Carbon::instance($mes)->startOfMonth()->startOfDay();
        }

        if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfDay();
        }

        return Carbon::parse($mes)->startOfMonth()->startOfDay();
    }

    public static function nome(CarbonInterface $mes): string
    {
        return self::NOMES[$mes->month];
    }

    public static function curto(CarbonInterface $mes): string
    {
        return self::CURTOS[$mes->month];
    }

    /**
     * "Outubro/2026".
     */
    public static function rotulo(CarbonInterface $mes): string
    {
        return ucfirst(self::NOMES[$mes->month]).'/'.$mes->year;
    }

    /**
     * "10/2026" → primeiro dia. Aceita "10/26", "OUT/2026", "OUTUBRO/2026".
     */
    public static function deTexto(string $texto): ?Carbon
    {
        $texto = mb_strtolower(trim($texto));

        if (preg_match('/^(\d{1,2})\s*\/\s*(\d{2}|\d{4})$/', $texto, $m)) {
            $ano = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];
            $mes = (int) $m[1];

            return $mes >= 1 && $mes <= 12 ? Carbon::create($ano, $mes, 1)->startOfDay() : null;
        }

        if (preg_match('/^([a-zç]{3,9})\s*\/\s*(\d{2}|\d{4})$/u', $texto, $m)) {
            $ano = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];

            foreach (self::NOMES as $numero => $nome) {
                if (str_starts_with($nome, mb_substr($m[1], 0, 3))) {
                    return Carbon::create($ano, $numero, 1)->startOfDay();
                }
            }
        }

        return null;
    }
}
