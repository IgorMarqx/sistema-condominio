<?php

namespace Tests\Unit;

use App\Services\Ingestao\Extratores\CagepaExtractor;
use App\Services\Ingestao\Extratores\EnergisaExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures sintéticas (tests/Fixtures/faturas/LEIAME.md). Trocar por PDFs reais anonimizados.
 */
class ExtratoresTest extends TestCase
{
    private function fixture(string $nome): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/faturas/'.$nome);
    }

    public function test_energisa_layout_em_colunas(): void
    {
        $texto = $this->fixture('energisa-condominio-sintetica.txt');
        $extrator = new EnergisaExtractor;

        $this->assertTrue($extrator->supports($texto));
        $this->assertFalse((new CagepaExtractor)->supports($texto));

        $d = $extrator->extract($texto);

        $this->assertSame('5/1234567-8', $d->identificador);
        $this->assertSame('2026-10-01', $d->mesRef?->toDateString());
        $this->assertSame(41257, $d->valorCentavos);
        $this->assertSame('2026-10-22', $d->vencimento?->toDateString());
        $this->assertSame('2026-09-21', $d->leituraAnterior?->toDateString());
        $this->assertSame('2026-10-20', $d->leituraAtual?->toDateString());
        $this->assertSame('2026-11-20', $d->proximaLeitura?->toDateString());
        $this->assertSame('512', $d->consumo);
        $this->assertSame(44, strlen((string) $d->chaveNf3e));
        $this->assertSame(41257, array_sum(array_column($d->itens, 'valor_centavos')));
        $this->assertSame('Consumo em kWh', $d->itens[0]['descricao']);
        $this->assertSame(1.0, $d->confianca());
    }

    public function test_energisa_rotulo_e_valor_na_mesma_linha(): void
    {
        $d = (new EnergisaExtractor)->extract($this->fixture('energisa-ap302-sintetica.txt'));

        $this->assertSame('5/7654321-0', $d->identificador);
        $this->assertSame('2026-10-01', $d->mesRef?->toDateString());
        $this->assertSame(18640, $d->valorCentavos);
        $this->assertSame('214', $d->consumo);
    }

    public function test_cagepa(): void
    {
        $d = (new CagepaExtractor)->extract($this->fixture('cagepa-condominio-sintetica.txt'));

        $this->assertSame('1234567-8', $d->identificador);
        $this->assertSame(23810, $d->valorCentavos);
        $this->assertSame('2026-10-25', $d->vencimento?->toDateString());
        $this->assertSame('28', $d->consumo);
        $this->assertNull($d->proximaLeitura);
        $this->assertSame([], $d->essenciaisFaltando());
    }

    public function test_texto_sem_campos_tem_confianca_baixa(): void
    {
        $d = (new EnergisaExtractor)->extract("ENERGISA\nqualquer coisa");

        $this->assertLessThan(0.8, $d->confianca());
        $this->assertContains('valor total', $d->essenciaisFaltando());
    }
}
