<?php

namespace Tests\Unit;

use App\Exceptions\RegraNegocio;
use App\Services\Rateio\CalculadoraRateio;
use PHPUnit\Framework\TestCase;

class CalculadoraRateioTest extends TestCase
{
    private const UNIDADES = [101, 102, 103, 201, 202, 203, 301, 302, 303, 401, 402, 403];

    public function test_exemplo_do_documento_resto_vai_para_as_tres_primeiras(): void
    {
        // Energisa 412,57 + Cagepa 238,10 ÷ 12: sobram 1 + 2 centavos, para 101 (energia), 102 e 103 (água).
        $calculo = new CalculadoraRateio;
        $energia = $calculo->dividir(41257, self::UNIDADES);
        $agua = $calculo->dividir(23810, self::UNIDADES);

        $total = fn (int $u) => $energia['valores'][$u] + $agua['valores'][$u] + 5000;

        $this->assertSame(10423, $total(101));
        $this->assertSame(10423, $total(102));
        $this->assertSame(10423, $total(103));
        $this->assertSame(10422, $total(302));
        $this->assertSame([101], $energia['recebem_resto']);
        $this->assertSame([102, 103], $agua['recebem_resto']);
        $this->assertSame(41257 + 23810, array_sum($energia['valores']) + array_sum($agua['valores']));
    }

    public function test_fracao_ideal_soma_exata(): void
    {
        $fracoes = array_fill_keys(self::UNIDADES, 100 / 12);
        $fracoes[101] = 100 / 12 + 1;
        $fracoes[403] = 100 / 12 - 1;

        $resultado = (new CalculadoraRateio)->dividir(65067, self::UNIDADES, 'fracao', $fracoes);

        $this->assertSame(65067, array_sum($resultado['valores']));
        $this->assertGreaterThan($resultado['valores'][403], $resultado['valores'][101]);
    }

    public function test_fracao_que_nao_soma_cem_e_recusada(): void
    {
        $this->expectException(RegraNegocio::class);

        (new CalculadoraRateio)->dividir(1000, [1, 2], 'fracao', [1 => 50, 2 => 40]);
    }
}
