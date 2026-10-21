<?php

namespace Tests\Feature;

use App\Enums\Concessionaria;
use App\Enums\StatusFatura;
use App\Models\Cobranca;
use App\Models\Fatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PDF da conta enviado à mão: pela unidade (conta individual) ou pelo síndico no painel.
 */
class ImportacaoPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predio();
        Carbon::setTestNow('2026-10-13 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function textoEnergisa(string $uc, int $valor): string
    {
        $r = number_format($valor / 100, 2, ',', '.');

        return "ENERGISA PARAÍBA - DISTRIBUIDORA DE ENERGIA S.A.\nCNPJ 09.095.183/0001-40\nNº da UC: {$uc}\nReferência: OUT/2026\n"
            ."Data de emissão: 13/10/2026\nVencimento: 20/10/2026\nTotal a pagar: R$ {$r}\nLeitura anterior: 12/09/2026\n"
            ."Leitura atual: 12/10/2026\nPróxima leitura: 11/11/2026\nConsumo (kWh): 214\nLinha digitável: 83640000001-8 86400033000-5 10012345678-2 02026101234-5";
    }

    public function test_upload_manual_pela_unidade(): void
    {
        $this->medidor('302', Concessionaria::Energisa, '5/7654321-0');
        $arquivo = UploadedFile::fake()->createWithContent('fatura.pdf', $this->pdf($this->textoEnergisa('5/7654321-0', 18640)));

        $this->actingAs($this->morador('302'))->post('/minhas-contas/upload', ['arquivo' => $arquivo])->assertSessionHas('sucesso');

        $this->assertSame('upload', Fatura::query()->value('origem'));
    }

    public function test_conta_do_condominio_importada_gera_as_cobrancas_ao_confirmar(): void
    {
        $admin = $this->sindico();
        $medidor = $this->medidor(null, Concessionaria::Energisa, '5/1234567-8');
        $arquivo = UploadedFile::fake()->createWithContent('fatura.pdf', $this->pdf($this->textoEnergisa('5/1234567-8', 41257)));

        $this->actingAs($admin)->post('/gestao/faturas/upload', ['arquivo' => $arquivo, 'medidor_id' => $medidor->id])->assertRedirect();

        $fatura = Fatura::query()->firstOrFail();
        $this->assertSame(StatusFatura::PendenteConfirmacao, $fatura->status);
        $this->assertSame(0, Cobranca::query()->count());

        $this->actingAs($admin)->post("/gestao/faturas/{$fatura->id}/confirmar")->assertSessionHas('sucesso');

        $this->assertSame(12, Cobranca::query()->count());
        $this->assertSame(41257, (int) Cobranca::query()->sum('valor_centavos'));
    }
}
