<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predio();
    }

    public function test_tela_de_entrada_lista_os_apartamentos(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p
            ->component('auth/entrar')
            ->where('apartamentos.0', ['401', '402', '403'])
            ->where('lembrado', null));
    }

    public function test_unidade_entra_pelo_numero_e_o_nome_vai_para_a_auditoria(): void
    {
        $this->morador('302');

        $this->post('/login', ['login' => '302', 'password' => 'password', 'nome' => 'Maria', 'lembrar' => true])
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertSame('Maria', session('nome_informado'));
        $this->assertDatabaseHas('sessoes_log', ['nome_informado' => 'Maria', 'metodo' => 'senha']);

        $registro = AuditLog::query()->where('acao', 'acesso.login')->latest('id')->first();
        $this->assertSame('302 — Maria', $registro->quem());
    }

    public function test_aparelho_lembrado_abre_bem_vindo(): void
    {
        $this->morador('302');
        $resposta = $this->post('/login', ['login' => '302', 'password' => 'password', 'nome' => 'Maria', 'lembrar' => true]);
        $cookie = collect($resposta->headers->getCookies())->first(fn ($c) => $c->getName() === 'condominio_aparelho');
        $this->post('/logout');

        $this->withUnencryptedCookie('condominio_aparelho', $cookie->getValue())
            ->get('/login')
            ->assertInertia(fn ($p) => $p->where('lembrado.apto', '302')->where('lembrado.nome', 'Maria')->where('entradaPorBiometria', false));
    }

    public function test_senha_errada_e_limite_de_tentativas(): void
    {
        $this->morador('302');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => '302', 'password' => 'errada'])->assertSessionHasErrors('login');
        }

        $this->post('/login', ['login' => '302', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_primeiro_acesso_obriga_trocar_a_senha(): void
    {
        $morador = $this->morador('101');
        $morador->update(['deve_trocar_senha' => true]);

        $this->actingAs($morador)->get('/')->assertRedirect('/trocar-senha');

        $this->actingAs($morador)->put('/trocar-senha', [
            'senha_atual' => 'password',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertRedirect('/');

        $this->assertFalse($morador->refresh()->deve_trocar_senha);
        $this->assertTrue(Hash::check('novaSenha123', $morador->password));
    }

    public function test_sindico_sem_2fa_vai_para_a_seguranca(): void
    {
        $sindico = User::factory()->create();

        $this->actingAs($sindico)->get('/gestao')->assertRedirect('/gestao/seguranca');
        $this->actingAs($sindico)->get('/gestao/seguranca')->assertOk();
    }

    public function test_unidade_nao_entra_na_gestao_e_gestao_nao_ve_a_area_da_unidade(): void
    {
        $this->actingAs($this->morador('302'))->get('/gestao')->assertRedirect('/');
        $this->actingAs($this->sindico())->get('/')->assertRedirect('/gestao');
    }

    public function test_conselho_le_mas_nao_altera(): void
    {
        $conselho = User::factory()->conselho()->create();

        $this->actingAs($conselho)->get('/gestao/caixa')->assertOk();
        $this->actingAs($conselho)->post('/gestao/auditoria/verificar')->assertForbidden();
    }

    public function test_sindico_redefine_senha_da_unidade_e_derruba_biometria(): void
    {
        $morador = $this->morador('302');
        $morador->update(['deve_trocar_senha' => false]);

        $resposta = $this->actingAs($this->sindico())->post('/gestao/unidades/'.$this->unidade('302')->id.'/senha');

        $senha = $resposta->getSession()->get('senha_gerada')['senha'];
        $this->assertSame(16, strlen($senha));
        $this->assertTrue(Hash::check($senha, $morador->refresh()->password));
        $this->assertTrue($morador->deve_trocar_senha);
        $this->assertDatabaseHas('audit_log', ['acao' => 'acesso.senha_redefinida', 'entidade_id' => $morador->id]);
    }

    public function test_sindico_adiciona_unidade_com_senha_provisoria(): void
    {
        $resposta = $this->actingAs($this->sindico())->post('/gestao/unidades', ['numero' => '104', 'fracao_ideal' => '7.5']);

        $senha = $resposta->getSession()->get('senha_gerada')['senha'];
        $unidade = Unidade::query()->where('numero', '104')->firstOrFail();
        $this->assertSame(1, $unidade->andar);
        $this->assertTrue($unidade->ativa);
        $this->assertSame('7.5000', $unidade->fracao_ideal);
        $this->assertTrue(Hash::check($senha, $unidade->acesso->password));
        $this->assertTrue($unidade->acesso->deve_trocar_senha);
        $this->assertDatabaseHas('audit_log', ['acao' => 'unidade.criada', 'entidade_id' => $unidade->id]);

        // Já aparece na grade do login.
        auth()->logout();
        $this->get('/login')->assertInertia(fn ($page) => $page->where('apartamentos', fn ($andares) => collect($andares)->flatten()->contains('104')));

        // Número repetido ou fora do formato não entra.
        $this->actingAs($this->sindico())->post('/gestao/unidades', ['numero' => '104'])->assertSessionHasErrors('numero');
        $this->actingAs($this->sindico())->post('/gestao/unidades', ['numero' => '04'])->assertSessionHasErrors('numero');
        $this->actingAs(User::factory()->conselho()->create())->post('/gestao/unidades', ['numero' => '105'])->assertForbidden();
        $this->assertSame(13, Unidade::query()->count());
    }

    public function test_biometria_da_gestao_abre_a_tela_de_biometria_no_celular(): void
    {
        $sindico = $this->sindico();
        $celular = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) Mobile Safari/537.36'];

        // Com senha: entra pelo 2FA e o aparelho não fica marcado.
        $this->post('/login', ['login' => $sindico->login, 'password' => 'password'])->assertRedirect('/two-factor-challenge');

        // Biometria cadastrada neste aparelho: o cookie passa a lembrar o e-mail da gestão.
        DB::table('passkeys')->insert(['user_id' => $sindico->id, 'name' => 'Android', 'credential_id' => 'credencial-sindico', 'credential' => '{}']);
        $resposta = $this->actingAs($sindico)->postJson('/aparelho/biometria')->assertOk();
        $cookie = collect($resposta->headers->getCookies())->first(fn ($c) => $c->getName() === 'condominio_aparelho')->getValue();
        $this->assertSame($sindico->login, json_decode($cookie, true)['gestao']);
        auth()->logout();

        $this->withUnencryptedCookie('condominio_aparelho', $cookie)->withServerVariables($celular)->get('/login')
            ->assertInertia(fn ($p) => $p->where('biometriaGestao.nome', 'Síndico')->where('biometriaGestao.papel', 'Síndico'));

        // No computador, ou sem passkey, volta à tela normal.
        $this->withUnencryptedCookie('condominio_aparelho', $cookie)->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/141'])->get('/login')
            ->assertInertia(fn ($p) => $p->where('biometriaGestao', null));
        DB::table('passkeys')->delete();
        $this->withUnencryptedCookie('condominio_aparelho', $cookie)->withServerVariables($celular)->get('/login')
            ->assertInertia(fn ($p) => $p->where('biometriaGestao', null));
    }

    public function test_trocar_apartamento_preserva_a_biometria_da_gestao(): void
    {
        $cookie = json_encode(['apto' => '302', 'nome' => null, 'biometria' => false, 'gestao' => 'sindico@condominio.local', 'entrada' => 'gestao']);

        $resposta = $this->withUnencryptedCookie('condominio_aparelho', $cookie)->post('/aparelho/esquecer');
        $novo = json_decode(collect($resposta->headers->getCookies())->first(fn ($c) => $c->getName() === 'condominio_aparelho')->getValue(), true);
        $this->assertSame(['gestao' => 'sindico@condominio.local', 'entrada' => 'gestao'], $novo);

        $resposta = $this->withUnencryptedCookie('condominio_aparelho', $cookie)->post('/aparelho/esquecer', ['gestao' => true]);
        $novo = json_decode(collect($resposta->headers->getCookies())->first(fn ($c) => $c->getName() === 'condominio_aparelho')->getValue(), true);
        $this->assertArrayNotHasKey('gestao', $novo);
        $this->assertSame('302', $novo['apto']);
    }
}
