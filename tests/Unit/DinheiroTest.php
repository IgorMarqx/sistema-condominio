<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function valores(): array
    {
        return [
            'vírgula' => ['104,22', 10422],
            'milhar e vírgula' => ['1.104,22', 110422],
            'ponto decimal' => ['104.22', 10422],
            'com R$' => ['R$ 3.482,15', 348215],
            'inteiro' => ['50', 5000],
            'uma casa' => ['12,5', 1250],
            'negativo' => ['-12,30', -1230],
        ];
    }

    #[DataProvider('valores')]
    public function test_converte_texto_em_centavos(string $texto, int $esperado): void
    {
        $this->assertSame($esperado, Dinheiro::paraCentavos($texto));
    }

    public function test_formata(): void
    {
        $this->assertSame('R$ 3.482,15', Dinheiro::formatar(348215));
        $this->assertSame('-R$ 0,01', Dinheiro::formatar(-1));
    }
}
