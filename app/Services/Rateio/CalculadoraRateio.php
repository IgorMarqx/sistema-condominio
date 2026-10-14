<?php

namespace App\Services\Rateio;

use App\Exceptions\RegraNegocio;

/**
 * Divisão em centavos inteiros. O resto vai 1 centavo por unidade, na ordem fixa (101, 102, …),
 * e a ordem continua de uma parcela para a outra: com energia sobrando 1 e água sobrando 2,
 * recebem 101 (energia), 102 e 103 (água) — ninguém leva dois centavos enquanto outro leva zero.
 */
class CalculadoraRateio
{
    private int $cursor = 0;

    /**
     * @param  list<int>  $unidades  ids na ordem fixa
     * @param  array<int, float|string|null>  $fracoes  percentual por unidade (regra "fracao"); soma = 100
     * @return array{valores: array<int, int>, resto: int, recebem_resto: list<int>}
     */
    public function dividir(int $totalCentavos, array $unidades, string $regra = 'igual', array $fracoes = []): array
    {
        $n = count($unidades);

        if ($n === 0) {
            throw new RegraNegocio('Não há unidades ativas para ratear.');
        }

        if ($regra === 'fracao') {
            $soma = array_sum(array_map(fn ($id) => (float) ($fracoes[$id] ?? 0), $unidades));

            if (abs($soma - 100) > 0.0001) {
                throw new RegraNegocio('As frações ideais somam '.number_format($soma, 4, ',', '.').'%; precisam somar 100%.');
            }

            $valores = [];

            foreach ($unidades as $id) {
                // Arredonda para baixo com precisão de 4 casas na fração (centésimos de ponto percentual).
                $valores[$id] = intdiv($totalCentavos * (int) round(((float) $fracoes[$id]) * 10000), 1_000_000);
            }
        } else {
            $base = intdiv($totalCentavos, $n);
            $valores = array_fill_keys($unidades, $base);
        }

        $resto = $totalCentavos - array_sum($valores);
        $recebem = [];

        for ($i = 0; $i < $resto; $i++) {
            $id = $unidades[$this->cursor % $n];
            $valores[$id]++;
            $recebem[] = $id;
            $this->cursor++;
        }

        return ['valores' => $valores, 'resto' => $resto, 'recebem_resto' => $recebem];
    }
}
