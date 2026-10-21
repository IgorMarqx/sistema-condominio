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
use App\Services\Rateio\RateioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ciclo completo: conta lançada (ou importada e confirmada) → cobranças → pagamento → caixa.
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

    /**
     * Energisa lançada no painel (valor e kWh): cobrança de energia para as 12 unidades.
     */
    private function lancarEnergia(): void
    {
        $this->medidor(null, Concessionaria::Energisa, '5/1234567-8');
        $this->actingAs($this->sindico())->post('/gestao/contas', ['concessionaria' => 'energisa', 'valor' => '412,57', 'consumo' => '512'])
            ->assertSessionHas('sucesso');
    }

    private function cobrancaDo(string $numero): Cobranca
    {
        return Cobranca::query()->whereHas('unidade', fn ($q) => $q->where('numero', $numero))->firstOrFail();
    }

    public function test_conta_lancada_no_painel_divide_igual_entre_as_unidades_ativas(): void
    {
        $this->medidor(null, Concessionaria::Energisa, '5/1234567-8');
        $this->medidor(null, Concessionaria::Cagepa, '1234567-8');
        $this->unidade('403')->update(['ativa' => false]);
        $admin = $this->sindico();

        $this->actingAs($admin)->post('/gestao/contas', ['concessionaria' => 'energisa', 'valor' => '412,57', 'consumo' => '512'])
            ->assertSessionHas('sucesso');

        // 41257 ÷ 11 = 3750 e sobram 7 centavos: 101, 102, 103, 201, 202, 203 e 301 levam 1 a mais.
        // A primeira conta do mês leva também a contribuição ao caixa (R$ 50,00).
        $energia = Cobranca::query()->with('unidade')->get()->mapWithKeys(fn ($c) => [$c->unidade->numero => $c->valor_centavos]);
        $this->assertCount(11, $energia);
        $this->assertArrayNotHasKey('403', $energia->all());
        $this->assertSame(3751 + 5000, $energia['101']);
        $this->assertSame(3750 + 5000, $energia['302']);
        $this->assertSame(41257 + 11 * 5000, $energia->sum());

        $fatura = Fatura::query()->firstOrFail();
        $this->assertSame('manual', $fatura->origem);
        $this->assertSame(StatusFatura::Confirmada, $fatura->status);
        $this->assertSame('512.000', (string) $fatura->consumo);
        $this->assertSame(11, Notificacao::query()->where('tipo', 'cobranca_gerada')->count());

        // A água vira outra cobrança do mesmo mês, sem a contribuição de novo.
        $this->actingAs($admin)->post('/gestao/contas', ['concessionaria' => 'cagepa', 'valor' => '238,10', 'consumo' => '28'])
            ->assertSessionHas('sucesso');

        $this->assertSame(2, Rateio::query()->where('mes_ref', '2026-10-01')->count());
        $agua = Cobranca::query()->where('rateio_id', Rateio::query()->max('id'))->with('unidade')->get()
            ->mapWithKeys(fn ($c) => [$c->unidade->numero => $c->valor_centavos]);
        $this->assertSame(2165, $agua['101']);
        $this->assertSame(2164, $agua['302']);
        $this->assertSame(23810, $agua->sum());

        // A mesma conta não entra duas vezes no mês.
        $this->actingAs($admin)->post('/gestao/contas', ['concessionaria' => 'energisa', 'valor' => '100,00'])->assertSessionHas('erro');
        $this->assertSame(2, Rateio::query()->count());
    }

    public function test_conta_sem_medidor_cadastrado_nao_e_lancada(): void
    {
        $this->actingAs($this->sindico())->post('/gestao/contas', ['concessionaria' => 'cagepa', 'valor' => '238,10'])->assertSessionHas('erro');

        $this->assertSame(0, Cobranca::query()->count());
    }

    public function test_marcar_paga_no_painel_entra_no_caixa_e_nao_paga_estorna(): void
    {
        $this->lancarEnergia();
        $admin = $this->sindico();
        $cobranca = $this->cobrancaDo('302');

        $this->actingAs($admin)->post("/gestao/cobrancas/{$cobranca->id}/paga")->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::Paga, $cobranca->refresh()->status);
        $lancamento = LancamentoCaixa::query()->where('origem_tipo', 'pagamento')->firstOrFail();
        $this->assertSame(8438, $lancamento->valor_centavos);
        $this->assertNull($lancamento->comprovante_s3);
        $this->assertSame(8438, app(CaixaService::class)->saldo());

        $this->actingAs($admin)->post("/gestao/cobrancas/{$cobranca->id}/paga")->assertSessionHas('erro');

        $this->actingAs($admin)->post("/gestao/cobrancas/{$cobranca->id}/nao-paga")->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::Aberta, $cobranca->refresh()->status);
        $this->assertSame(0, app(CaixaService::class)->saldo());
        $this->assertTrue($lancamento->refresh()->estornadoPor()->exists());
    }

    public function test_marcar_paga_confirma_o_comprovante_ja_enviado(): void
    {
        $this->lancarEnergia();
        $cobranca = $this->cobrancaDo('101');

        $this->actingAs($this->morador('101'))->post("/cobranca/{$cobranca->id}/comprovante", [
            'arquivo' => UploadedFile::fake()->create('pix.pdf', 10, 'application/pdf'),
            'pago_em' => '2026-10-12',
        ]);

        $this->actingAs($this->sindico())->post("/gestao/cobrancas/{$cobranca->id}/paga")->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::Paga, $cobranca->refresh()->status);
        $this->assertSame(1, LancamentoCaixa::query()->count());
        $this->assertSame($cobranca->pagamentos()->value('sha256'), LancamentoCaixa::query()->value('comprovante_sha256'));
    }

    public function test_comprovante_confirmado_vira_entrada_no_caixa(): void
    {
        $this->lancarEnergia();
        $admin = $this->sindico();
        $cobranca = $this->cobrancaDo('302');
        $morador = $this->morador('302');

        $this->actingAs($morador)->get('/cobranca')->assertInertia(fn ($p) => $p
            ->component('unidade/cobranca')
            ->where('cobranca.valor', 8438)
            ->where('cobranca.servico', 'Energia')
            ->where('cobranca.pix', fn ($pix) => str_starts_with($pix, '000201') && str_contains($pix, '84.38')));

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
        $this->assertSame(8438, $lancamento->valor_centavos);
        $this->assertSame($pagamento->sha256, $lancamento->comprovante_sha256);
        $this->assertSame(8438, app(CaixaService::class)->saldo());

        // Estornar o lançamento reabre a cobrança.
        $this->actingAs($admin)->post("/gestao/caixa/lancamentos/{$lancamento->id}/estornar", ['motivo' => 'Pix não caiu'])->assertSessionHas('sucesso');
        $this->assertSame(StatusCobranca::Aberta, $cobranca->refresh()->status);
    }

    public function test_comprovante_recusado_volta_a_cobranca_para_aberta(): void
    {
        $this->lancarEnergia();
        $cobranca = $this->cobrancaDo('101');

        $this->actingAs($this->morador('101'))->post("/cobranca/{$cobranca->id}/comprovante", [
            'arquivo' => UploadedFile::fake()->create('pix.pdf', 10, 'application/pdf'),
            'pago_em' => '2026-10-12',
        ]);

        $this->actingAs($this->sindico())->post('/gestao/pagamentos/'.$cobranca->pagamentos()->value('id').'/recusar', ['motivo' => 'Não está no extrato'])
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusCobranca::Aberta, $cobranca->refresh()->status);
        $this->assertSame(0, LancamentoCaixa::query()->count());
    }

    public function test_unidade_nao_ve_nem_paga_cobranca_de_outra(): void
    {
        $this->lancarEnergia();
        $deOutro = $this->cobrancaDo('101');

        $this->actingAs($this->morador('302'))->get("/cobranca/{$deOutro->id}")->assertNotFound();
        $this->actingAs($this->morador('302'))->post("/cobranca/{$deOutro->id}/comprovante", [
            'arquivo' => UploadedFile::fake()->create('pix.pdf', 10, 'application/pdf'),
            'pago_em' => '2026-10-12',
        ])->assertNotFound();
    }

    public function test_ajuste_entra_na_primeira_conta_do_mes(): void
    {
        $admin = $this->sindico();
        app(RateioService::class)->criarAjuste($this->unidade('302'), -1000, 'Crédito de setembro', 'Cobrado a mais', $admin);

        $this->lancarEnergia();

        $cobranca = $this->cobrancaDo('302');
        $this->assertSame(8438 - 1000, $cobranca->valor_centavos);
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
