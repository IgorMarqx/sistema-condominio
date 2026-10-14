<?php

namespace Tests\Feature;

use App\Enums\CategoriaLancamento;
use App\Exceptions\RegistroImutavel;
use App\Models\ContaCaixa;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Services\Caixa\CaixaService;
use App\Services\Caixa\FechamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CaixaTest extends TestCase
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

    private function comprovante(): UploadedFile
    {
        return UploadedFile::fake()->create('comprovante.pdf', 20, 'application/pdf');
    }

    public function test_lancamento_exige_comprovante(): void
    {
        $this->actingAs($this->sindico())
            ->post('/gestao/caixa/lancamentos', [
                'conta' => 'corrente', 'tipo' => 'saida', 'valor' => '120,00', 'data' => '2026-10-10',
                'categoria' => 'manutencao', 'descricao' => 'Boia',
            ])
            ->assertSessionHasErrors('comprovante');

        $this->actingAs($this->sindico())
            ->post('/gestao/caixa/lancamentos', [
                'conta' => 'corrente', 'tipo' => 'saida', 'valor' => '120,00', 'data' => '2026-10-10',
                'categoria' => 'manutencao', 'descricao' => 'Boia', 'comprovante' => $this->comprovante(),
            ])
            ->assertSessionHas('sucesso');

        $this->assertSame(-12000, app(CaixaService::class)->saldo());
    }

    public function test_estorno_nao_altera_o_original_e_zera_o_efeito(): void
    {
        $caixa = app(CaixaService::class);
        $admin = $this->sindico();
        $original = $caixa->lancar(ContaCaixa::corrente(), Carbon::parse('2026-10-05'), 'entrada', 10422, CategoriaLancamento::Cobranca, 'Duplicado', $admin, $this->comprovante());

        $this->actingAs($admin)->post("/gestao/caixa/lancamentos/{$original->id}/estornar", ['motivo' => 'Lançado em dobro'])->assertSessionHas('sucesso');

        $this->assertSame(0, $caixa->saldo());
        $this->assertSame('Duplicado', $original->refresh()->descricao);
        $this->assertNotNull($original->estornadoPor);
        $this->assertSame(['entradas' => 0, 'saidas' => 0], $caixa->movimentoDoMes(Carbon::parse('2026-10-01')));

        // Segundo estorno do mesmo lançamento é recusado.
        $this->actingAs($admin)->post("/gestao/caixa/lancamentos/{$original->id}/estornar", ['motivo' => 'de novo'])->assertSessionHas('erro');
    }

    public function test_lancamento_e_append_only(): void
    {
        $lancamento = app(CaixaService::class)->lancar(ContaCaixa::corrente(), Carbon::parse('2026-10-05'), 'entrada', 100, CategoriaLancamento::Outros, 'x', $this->sindico(), $this->comprovante());

        $this->expectException(RegistroImutavel::class);
        LancamentoCaixa::query()->find($lancamento->id)->delete();
    }

    public function test_transferencia_gera_dois_lancamentos_e_nao_mexe_no_total(): void
    {
        $caixa = app(CaixaService::class);
        $admin = $this->sindico();
        $caixa->lancar(ContaCaixa::corrente(), Carbon::parse('2026-10-01'), 'entrada', 50000, CategoriaLancamento::Outros, 'Saldo', $admin, $this->comprovante());

        $caixa->transferir(ContaCaixa::corrente(), ContaCaixa::reserva(), 20000, Carbon::parse('2026-10-02'), 'Reserva', $admin, $this->comprovante());

        $this->assertSame(['corrente' => 30000, 'reserva' => 20000], $caixa->saldosPorConta());
        $this->assertSame(50000, $caixa->saldo());
        $this->assertSame(1, LancamentoCaixa::query()->whereNotNull('grupo')->distinct()->count('grupo'));
    }

    public function test_fechamento_concilia_trava_o_mes_e_gera_pdf(): void
    {
        $caixa = app(CaixaService::class);
        $admin = $this->sindico();
        $caixa->lancar(ContaCaixa::corrente(), Carbon::parse('2026-09-01'), 'entrada', 190324, CategoriaLancamento::Outros, 'Saldo inicial', $admin, $this->comprovante());

        $fechamentos = app(FechamentoService::class);

        // Diferença sem explicação não fecha.
        $this->actingAs($admin)->post('/gestao/fechamento', ['mes' => '2026-09', 'saldos' => ['corrente' => '1.900,00', 'reserva' => '0,00']])
            ->assertSessionHas('erro');

        $this->actingAs($admin)->post('/gestao/fechamento', ['mes' => '2026-09', 'saldos' => ['corrente' => '1.903,24', 'reserva' => '0,00']])
            ->assertSessionHas('sucesso');

        $fechamento = Fechamento::query()->firstOrFail();
        $this->assertSame(0, $fechamento->diferenca_centavos);
        $this->assertNotNull($fechamento->pdf_s3);
        $this->assertNotNull($fechamento->hash_auditoria);
        $this->assertSame('2026-10', $fechamentos->proximoMes()?->format('Y-m'));

        // Mês fechado não aceita lançamento com data nele.
        $this->actingAs($admin)->post('/gestao/caixa/lancamentos', [
            'conta' => 'corrente', 'tipo' => 'saida', 'valor' => '10', 'data' => '2026-09-30',
            'categoria' => 'outros', 'descricao' => 'atrasado', 'comprovante' => $this->comprovante(),
        ])->assertSessionHas('erro');
    }

    public function test_mes_em_andamento_nao_fecha(): void
    {
        $caixa = app(CaixaService::class);
        $caixa->lancar(ContaCaixa::corrente(), Carbon::parse('2026-10-01'), 'entrada', 100, CategoriaLancamento::Outros, 'x', $this->sindico(), $this->comprovante());

        $this->assertFalse(app(FechamentoService::class)->previa(Carbon::parse('2026-10-01'))['pode_fechar']);
    }
}
