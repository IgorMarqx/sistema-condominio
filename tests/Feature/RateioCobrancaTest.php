<?php

namespace Tests\Feature;

use App\Enums\Concessionaria;
use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Models\LancamentoCaixa;
use App\Models\Notificacao;
use App\Models\Rateio;
use App\Services\Caixa\CaixaService;
use App\Services\Configuracoes;
use App\Services\Faturas\FaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ciclo completo: fatura confirmada → rateio → cobrança → comprovante → confirmação → caixa.
 */
class RateioCobrancaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predio();
        Carbon::setTestNow('2026-10-13 12:00:00');
        app(Configuracoes::class)->salvar(['pix_chave' => 'caixa@condominio.exemplo', 'pix_recebedor' => 'Condominio Exemplo']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: Fatura, 1: Fatura}
     */
    private function faturasConfirmadas(): array
    {
        $admin = $this->sindico();
        $faturas = app(FaturaService::class);
        $energia = $this->medidor(null, Concessionaria::Energisa, '5/1234567-8');
        $agua = $this->medidor(null, Concessionaria::Cagepa, '1234567-8');

        $e = $faturas->registrar($energia, $this->dados($energia, '2026-10', 41257, 512), Carbon::parse('2026-10-01'), null, null, 'upload');
        $a = $faturas->registrar($agua, $this->dados($agua, '2026-10', 23810, 28), Carbon::parse('2026-10-01'), null, null, 'upload');

        $this->assertSame(StatusFatura::PendenteConfirmacao, $e->status);

        return [$faturas->confirmar($e, $admin), $faturas->confirmar($a, $admin)];
    }

    public function test_rateio_do_exemplo_do_documento(): void
    {
        [$e, $a] = $this->faturasConfirmadas();
        $admin = $this->sindico();

        $this->actingAs($admin)->post('/gestao/rateio', ['mes' => '2026-10', 'faturas' => [$e->id, $a->id]])->assertSessionHas('sucesso');
        $rateio = Rateio::query()->firstOrFail();
        $this->assertSame('rascunho', $rateio->status);
        $this->assertSame(0, Cobranca::query()->count());

        $this->actingAs($admin)->post("/gestao/rateio/{$rateio->id}/confirmar")->assertSessionHas('sucesso');

        $valores = Cobranca::query()->with('unidade')->get()->mapWithKeys(fn ($c) => [$c->unidade->numero => $c->valor_centavos]);
        $this->assertSame(10423, $valores['101']);
        $this->assertSame(10423, $valores['103']);
        $this->assertSame(10422, $valores['302']);
        $this->assertSame(41257 + 23810 + 12 * 5000, $valores->sum());

        // Vencimento interno = vencimento da concessionária (22/10) − 5 dias.
        $this->assertSame('2026-10-17', $rateio->refresh()->vencimento->toDateString());
        $this->assertSame(12, Notificacao::query()->where('tipo', 'cobranca_gerada')->count());

        // Confirmado fica travado: não confirma de novo nem descarta.
        $this->actingAs($admin)->post("/gestao/rateio/{$rateio->id}/confirmar")->assertSessionHas('erro');
        $this->actingAs($admin)->delete("/gestao/rateio/{$rateio->id}")->assertSessionHas('erro');
    }

    public function test_comprovante_confirmado_vira_entrada_no_caixa(): void
    {
        [$e, $a] = $this->faturasConfirmadas();
        $admin = $this->sindico();
        $this->actingAs($admin)->post('/gestao/rateio', ['mes' => '2026-10', 'faturas' => [$e->id, $a->id]]);
        $this->actingAs($admin)->post('/gestao/rateio/'.Rateio::query()->value('id').'/confirmar');

        $cobranca = Cobranca::query()->whereHas('unidade', fn ($q) => $q->where('numero', '302'))->firstOrFail();
        $morador = $this->morador('302');

        $this->actingAs($morador)->get('/cobranca')->assertInertia(fn ($p) => $p
            ->component('unidade/cobranca')
            ->where('cobranca.valor', 10422)
            ->where('cobranca.pix', fn ($pix) => str_starts_with($pix, '000201') && str_contains($pix, '104.22')));

        $this->actingAs($morador)->withSession(['nome_informado' => 'Maria'])->post("/cobranca/{$cobranca->id}/comprovante", [
            'arquivo' => UploadedFile::fake()->image('pix.jpg'),
            'pago_em' => '2026-10-12',
        ])->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::AguardandoConfirmacao, $cobranca->refresh()->status);
        $pagamento = $cobranca->pagamentos()->firstOrFail();
        $this->assertSame('Maria', $pagamento->nome_informado);

        $this->actingAs($admin)->post("/gestao/pagamentos/{$pagamento->id}/confirmar")->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::Paga, $cobranca->refresh()->status);
        $lancamento = LancamentoCaixa::query()->where('origem_tipo', 'pagamento')->firstOrFail();
        $this->assertSame(10422, $lancamento->valor_centavos);
        $this->assertSame($pagamento->sha256, $lancamento->comprovante_sha256);
        $this->assertSame(10422, app(CaixaService::class)->saldo());

        // Estornar o lançamento reabre a cobrança.
        $this->actingAs($admin)->post("/gestao/caixa/lancamentos/{$lancamento->id}/estornar", ['motivo' => 'Pix não caiu'])->assertSessionHas('sucesso');
        $this->assertSame(StatusCobranca::Aberta, $cobranca->refresh()->status);
    }

    public function test_comprovante_recusado_volta_a_cobranca_para_aberta(): void
    {
        [$e, $a] = $this->faturasConfirmadas();
        $admin = $this->sindico();
        $this->actingAs($admin)->post('/gestao/rateio', ['mes' => '2026-10', 'faturas' => [$e->id, $a->id]]);
        $this->actingAs($admin)->post('/gestao/rateio/'.Rateio::query()->value('id').'/confirmar');
        $cobranca = Cobranca::query()->whereHas('unidade', fn ($q) => $q->where('numero', '101'))->firstOrFail();

        $this->actingAs($this->morador('101'))->post("/cobranca/{$cobranca->id}/comprovante", [
            'arquivo' => UploadedFile::fake()->create('pix.pdf', 10, 'application/pdf'),
            'pago_em' => '2026-10-12',
        ]);

        $this->actingAs($admin)->post('/gestao/pagamentos/'.$cobranca->pagamentos()->value('id').'/recusar', ['motivo' => 'Não está no extrato'])
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::Aberta, $cobranca->refresh()->status);
        $this->assertSame(0, LancamentoCaixa::query()->count());
    }

    public function test_unidade_nao_ve_nem_paga_cobranca_de_outra(): void
    {
        [$e, $a] = $this->faturasConfirmadas();
        $admin = $this->sindico();
        $this->actingAs($admin)->post('/gestao/rateio', ['mes' => '2026-10', 'faturas' => [$e->id, $a->id]]);
        $this->actingAs($admin)->post('/gestao/rateio/'.Rateio::query()->value('id').'/confirmar');
        $deOutro = Cobranca::query()->whereHas('unidade', fn ($q) => $q->where('numero', '101'))->firstOrFail();

        $this->actingAs($this->morador('302'))->get("/cobranca/{$deOutro->id}")->assertNotFound();
        $this->actingAs($this->morador('302'))->post("/cobranca/{$deOutro->id}/comprovante", [
            'arquivo' => UploadedFile::fake()->create('pix.pdf', 10, 'application/pdf'),
            'pago_em' => '2026-10-12',
        ])->assertNotFound();
    }

    public function test_ajuste_entra_na_proxima_cobranca(): void
    {
        [$e, $a] = $this->faturasConfirmadas();
        $admin = $this->sindico();

        $this->actingAs($admin)->post('/gestao/ajustes', [
            'unidade' => '302', 'tipo' => 'credito', 'valor' => '10,00', 'descricao' => 'Crédito de setembro', 'motivo' => 'Cobrado a mais',
        ])->assertSessionHas('sucesso');

        $this->actingAs($admin)->post('/gestao/rateio', ['mes' => '2026-10', 'faturas' => [$e->id, $a->id]]);
        $this->actingAs($admin)->post('/gestao/rateio/'.Rateio::query()->value('id').'/confirmar');

        $cobranca = Cobranca::query()->whereHas('unidade', fn ($q) => $q->where('numero', '302'))->firstOrFail();
        $this->assertSame(10422 - 1000, $cobranca->valor_centavos);
        $this->assertTrue($cobranca->itens()->where('tipo', 'ajuste')->exists());
    }

    public function test_fatura_confirmada_e_paga_pelo_caixa(): void
    {
        [$e] = $this->faturasConfirmadas();

        $this->actingAs($this->sindico())->post("/gestao/faturas/{$e->id}/pagar", [
            'data' => '2026-10-12',
            'comprovante' => UploadedFile::fake()->create('energisa.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('sucesso');

        $this->assertSame(-41257, app(CaixaService::class)->saldo());
        $this->assertNotNull($e->refresh()->paga_em);
        $this->assertSame('quitada', $e->competencia->status->value);
    }

    public function test_correcao_na_revisao_fica_auditada_com_motivo(): void
    {
        $energia = $this->medidor(null, Concessionaria::Energisa, '5/1234567-8');
        $fatura = app(FaturaService::class)->registrar($energia, $this->dados($energia, '2026-10', 41257), Carbon::parse('2026-10-01'), null, null, 'upload');

        $this->actingAs($this->sindico())->patch("/gestao/faturas/{$fatura->id}", ['valor' => '412,75', 'motivo' => 'Extrator trocou dígitos'])
            ->assertSessionHas('sucesso');

        $this->assertSame(41275, $fatura->refresh()->valor_centavos);
        $this->assertDatabaseHas('audit_log', ['acao' => 'fatura.corrigida', 'entidade_id' => $fatura->id]);
    }
}
