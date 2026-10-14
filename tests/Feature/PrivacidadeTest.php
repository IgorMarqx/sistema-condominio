<?php

namespace Tests\Feature;

use App\Enums\Concessionaria;
use App\Models\Anexo;
use App\Models\Fatura;
use App\Services\Arquivos\Armazenamento;
use App\Services\Faturas\FaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LGPD: fatura individual só para a própria unidade; o síndico vê valor só com autorização.
 */
class PrivacidadeTest extends TestCase
{
    use RefreshDatabase;

    private Fatura $fatura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predio();

        $medidor = $this->medidor('302', Concessionaria::Energisa, '5/7654321-0');
        $arquivo = app(Armazenamento::class)->guardarConteudo($this->pdf('fatura'), 'faturas', 'pdf');
        $anexo = Anexo::query()->create(['nome' => 'f.pdf', 's3_key' => $arquivo['caminho'], 'sha256' => $arquivo['sha256'], 'tamanho' => $arquivo['tamanho']]);
        $this->fatura = app(FaturaService::class)->registrar($medidor, $this->dados($medidor, '2026-10', 18640), Carbon::parse('2026-10-01'), $anexo, null, 'upload');
    }

    public function test_pdf_individual_so_para_a_propria_unidade(): void
    {
        $this->actingAs($this->morador('302'))->get("/arquivos/fatura/{$this->fatura->id}")->assertOk();
        $this->actingAs($this->morador('101'))->get("/arquivos/fatura/{$this->fatura->id}")->assertNotFound();
        $this->actingAs($this->sindico())->get("/arquivos/fatura/{$this->fatura->id}")->assertNotFound();
    }

    public function test_sindico_ve_status_mas_nao_o_valor_sem_autorizacao(): void
    {
        $this->actingAs($this->sindico())->get('/gestao/faturas?mes=2026-10&aba=unidades')
            ->assertInertia(fn ($p) => $p->where('unidades.linhas.7.numero', '302')
                ->missing('unidades.linhas.7.faturas.0.valor')
                ->where('unidades.linhas.7.faturas.0.concessionaria', 'energisa'));

        $this->actingAs($this->morador('302'))->put('/minhas-contas/privacidade', ['autoriza' => true]);

        $this->actingAs($this->sindico())->get('/gestao/faturas?mes=2026-10&aba=unidades')
            ->assertInertia(fn ($p) => $p->where('unidades.linhas.7.faturas.0.valor', 18640));
        $this->actingAs($this->sindico())->get("/arquivos/fatura/{$this->fatura->id}")->assertOk();
    }

    public function test_unidade_exclui_o_proprio_historico(): void
    {
        $this->actingAs($this->morador('302'))->delete('/minhas-contas/historico', ['confirmacao' => 'EXCLUIR'])->assertSessionHas('sucesso');

        $this->assertSame(0, Fatura::query()->count());
        $this->assertSame(0, Anexo::query()->count());
        $this->assertDatabaseHas('audit_log', ['acao' => 'unidade.historico_excluido']);
    }

    public function test_outra_unidade_nao_corrige_fatura_alheia(): void
    {
        $this->actingAs($this->morador('101'))->patch("/minhas-contas/faturas/{$this->fatura->id}", ['valor' => '1,00'])->assertSessionHas('erro');
        $this->assertSame(18640, $this->fatura->refresh()->valor_centavos);
    }
}
