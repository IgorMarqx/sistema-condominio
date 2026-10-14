<?php

namespace Tests\Feature;

use App\Enums\Concessionaria;
use App\Enums\StatusFatura;
use App\Models\EmailRecebido;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Models\Notificacao;
use App\Services\Ingestao\CaixasEntradaService;
use App\Services\Ingestao\RecebedorEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Pipeline da seção 4: roteamento, classificação, deduplicação, extração, vínculo e validação.
 */
class IngestaoEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $enderecoCondominio;

    private string $endereco302;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predio();
        $caixas = app(CaixasEntradaService::class);
        $this->enderecoCondominio = $caixas->garantir(null)->endereco;
        $this->endereco302 = $caixas->garantir($this->unidade('302'))->endereco;
    }

    private function texto302(int $valor = 18640, string $uc = '5/7654321-0'): string
    {
        $r = number_format($valor / 100, 2, ',', '.');

        return "ENERGISA PARAÍBA - DISTRIBUIDORA DE ENERGIA S.A.\nCNPJ 09.095.183/0001-40\nNº da UC: {$uc}\nReferência: OUT/2026\n"
            ."Data de emissão: 13/10/2026\nVencimento: 20/10/2026\nTotal a pagar: R$ {$r}\nLeitura anterior: 12/09/2026\n"
            ."Leitura atual: 12/10/2026\nPróxima leitura: 11/11/2026\nConsumo (kWh): 214\nLinha digitável: 83640000001-8 86400033000-5 10012345678-2 02026101234-5";
    }

    private function receber(string $eml, array $envelope = []): ?EmailRecebido
    {
        return app(RecebedorEmail::class)->receber($eml, $envelope)?->refresh();
    }

    public function test_fatura_da_energisa_vira_fatura_da_unidade_sem_digitacao(): void
    {
        $email = $this->receber($this->eml($this->endereco302, 'faturadigital@energisa.com.br', 'Sua fatura', $this->pdf($this->texto302())));

        $this->assertSame('processado', $email->status);
        $this->assertSame('fatura', $email->tipo);

        $fatura = Fatura::query()->firstOrFail();
        $this->assertSame(StatusFatura::Processada, $fatura->status);
        $this->assertSame(18640, $fatura->valor_centavos);
        $this->assertSame('2026-10-20', $fatura->vencimento->toDateString());
        $this->assertSame('email', $fatura->origem);
        $this->assertNotNull($fatura->texto_extraido);
        $this->assertSame('energisa', $fatura->extrator);

        // A 1ª fatura cadastra o medidor da unidade; o síndico é avisado.
        $medidor = Medidor::query()->firstOrFail();
        $this->assertSame($this->unidade('302')->id, $medidor->unidade_id);
        $this->assertTrue(Notificacao::query()->where('tipo', 'medidor_novo')->exists());

        // Próxima leitura prevista vem da fatura.
        $this->assertDatabaseHas('competencias', ['medidor_id' => $medidor->id, 'mes_ref' => '2026-11-01', 'leitura_prevista' => '2026-11-11', 'leitura_prevista_origem' => 'fatura']);
    }

    public function test_mesmo_pdf_por_encaminhamento_nao_duplica(): void
    {
        $pdf = $this->pdf($this->texto302());
        $this->receber($this->eml($this->endereco302, 'faturadigital@energisa.com.br', 'Direto', $pdf));
        $segundo = $this->receber($this->eml($this->endereco302, 'faturadigital@energisa.com.br', 'Fwd: encaminhado', $pdf));

        $this->assertSame('duplicado', $segundo->status);
        $this->assertSame(1, Fatura::query()->count());
    }

    public function test_endereco_desconhecido_e_descartado(): void
    {
        $this->assertNull($this->receber($this->eml('ap999-zzzz@faturas.teste', 'x@energisa.com.br', 'Oi', $this->pdf($this->texto302()))));
        $this->assertSame(0, EmailRecebido::query()->count());
    }

    public function test_destinatario_do_envelope_vale_no_encaminhamento(): void
    {
        // O Gmail mantém o To: original (o e-mail pessoal); o RCPT TO é o nosso endereço.
        $email = $this->receber(
            $this->eml('maria@gmail.com', 'faturadigital@energisa.com.br', 'Fwd', $this->pdf($this->texto302())),
            [$this->endereco302],
        );

        $this->assertSame('processado', $email->status);
    }

    public function test_confirmacao_do_gmail_mostra_o_codigo_e_nao_vira_fatura(): void
    {
        $eml = $this->eml(
            $this->endereco302,
            'forwarding-noreply@google.com',
            '(#482913057) Confirmação de encaminhamento do Gmail',
            null,
            "Código de confirmação: 482913057\nhttps://mail-settings.google.com/mail/vf-abc",
        );

        $email = $this->receber($eml);

        $this->assertSame('verificacao', $email->tipo);
        $this->assertSame('482913057', $email->verificacao_codigo);
        $this->assertStringStartsWith('https://mail-settings.google.com', (string) $email->verificacao_link);
        $this->assertSame(0, Fatura::query()->count());

        $this->actingAs($this->morador('302'))->get('/receber-faturas')
            ->assertInertia(fn ($p) => $p->where('verificacao.codigo', '482913057')->where('verificacao.gmail', true));
    }

    public function test_sem_pdf_registra_links_sem_abrir_e_vai_para_revisao(): void
    {
        $email = $this->receber($this->eml($this->endereco302, 'faturadigital@energisa.com.br', 'Sua fatura', null, 'Acesse https://agencia.energisa.com.br/fatura/123 para baixar.'));

        $this->assertSame('revisao', $email->status);
        $this->assertSame(['https://agencia.energisa.com.br/fatura/123'], $email->links);
    }

    public function test_medidor_de_outra_unidade_e_bloqueado(): void
    {
        $this->medidor('101', Concessionaria::Energisa, '5/7654321-0');

        $email = $this->receber($this->eml($this->endereco302, 'faturadigital@energisa.com.br', 'Sua fatura', $this->pdf($this->texto302())));

        $this->assertSame('revisao', $email->status);
        $this->assertSame(0, Fatura::query()->count());
        $this->assertTrue(Notificacao::query()->where('tipo', 'medidor_outra_unidade')->exists());
    }

    public function test_fatura_do_condominio_aguarda_o_sindico(): void
    {
        $this->medidor(null, Concessionaria::Energisa, '5/1234567-8');

        $this->receber($this->eml($this->enderecoCondominio, 'faturadigital@energisa.com.br', 'Fatura', $this->pdf($this->texto302(41257, '5/1234567-8'))));

        $fatura = Fatura::query()->firstOrFail();
        $this->assertSame(StatusFatura::PendenteConfirmacao, $fatura->status);
        $this->assertTrue(Notificacao::query()->where('tipo', 'fatura_condominio')->exists());
    }

    public function test_remetente_nao_reconhecido_vai_para_revisao_mesmo_lendo_tudo(): void
    {
        $this->receber($this->eml($this->endereco302, 'maria@gmail.com', 'minha conta', $this->pdf($this->texto302()), 'segue', 'spf=pass dkim=pass'));

        $fatura = Fatura::query()->firstOrFail();
        $this->assertSame(StatusFatura::PendenteRevisao, $fatura->status);
        $this->assertStringContainsString('Remetente não reconhecido', (string) $fatura->motivo_revisao);
    }

    public function test_condominio_sem_medidor_cadastrado_fica_em_revisao(): void
    {
        $email = $this->receber($this->eml($this->enderecoCondominio, 'faturadigital@energisa.com.br', 'Fatura', $this->pdf($this->texto302(41257, '5/1234567-8'))));

        $this->assertSame('revisao', $email->status);
        $this->assertStringContainsString('não está cadastrado', (string) $email->erro);
    }

    public function test_webhook_bruto_exige_token(): void
    {
        config(['condominio.webhook_email_token' => 'segredo-longo']);
        $eml = $this->eml($this->endereco302, 'faturadigital@energisa.com.br', 'Sua fatura', $this->pdf($this->texto302()));

        $this->call('POST', '/webhooks/email/bruto/errado', [], [], [], ['CONTENT_TYPE' => 'message/rfc822'], $eml)->assertForbidden();
        $this->call('POST', '/webhooks/email/bruto/segredo-longo', [], [], [], ['CONTENT_TYPE' => 'message/rfc822'], $eml)
            ->assertOk()->assertJson(['aceito' => true]);

        $this->assertSame(1, Fatura::query()->count());
    }

    public function test_regenerar_endereco_desativa_o_antigo(): void
    {
        $antigo = $this->endereco302;
        $this->actingAs($this->morador('302'))->post('/receber-faturas/regenerar')->assertSessionHas('sucesso');

        $this->assertNull($this->receber($this->eml($antigo, 'faturadigital@energisa.com.br', 'Fatura', $this->pdf($this->texto302()))));
    }

    public function test_upload_manual_pela_unidade(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('fatura.pdf', $this->pdf($this->texto302()));

        $this->actingAs($this->morador('302'))->post('/minhas-contas/upload', ['arquivo' => $arquivo])->assertSessionHas('sucesso');

        $this->assertSame('upload', Fatura::query()->value('origem'));
    }
}
