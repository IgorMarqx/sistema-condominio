<?php

namespace Tests\Feature;

use App\Mail\RecuperacaoSenhaMail;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RecuperacaoSenhaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.gzappy.api_key' => 'chave-teste', 'app.url' => 'https://condominio.teste', 'inertia.ssr.enabled' => false]);
    }

    public function test_tela_e_canais_mascarados_por_cpf_email_e_apartamento(): void
    {
        $user = User::factory()->create(['cpf' => '12489723413', 'email' => 'igor@example.com', 'telefone' => '83999991492']);

        $this->get('/recuperar-senha')->assertOk()->assertInertia(fn ($page) => $page->component('auth/recuperar-senha'));

        foreach (['124.897.234-13', $user->login] as $identificador) {
            $this->postJson('/recuperar-senha/canais', compact('identificador'))->assertOk()->assertExactJson([
                'canais' => ['email' => 'ig•••@example.com', 'whatsapp' => '(83) •••••-1492'],
            ]);
        }

        $this->predio();
        $morador = $this->morador('302');
        $morador->update(['email_recuperacao' => 'morador@example.com']);
        $this->postJson('/recuperar-senha/canais', ['identificador' => '302'])->assertOk()->assertJsonPath('canais.email', 'mo•••@example.com');
        Mail::assertNothingSent();
    }

    public function test_desconhecido_inativo_e_unidade_desativada_nao_oferecem_canais(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);
        $this->predio();
        $this->morador('302')->unidade->update(['ativa' => false]);

        foreach (['inexistente', $inativo->login, '302'] as $identificador) {
            $this->postJson('/recuperar-senha/canais', compact('identificador'))->assertOk()->assertExactJson(['canais' => ['email' => null, 'whatsapp' => null]]);
            $this->postJson('/recuperar-senha/enviar', ['identificador' => $identificador, 'canal' => 'email'])->assertUnprocessable();
        }

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_email_contem_link_valido_que_so_pode_ser_usado_uma_vez(): void
    {
        $user = User::factory()->comDoisFatores()->create(['deve_trocar_senha' => true]);
        $segredo = $user->two_factor_secret;
        DB::table('sessions')->insert(['id' => 'sessao-antiga', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('passkeys')->insert(['user_id' => $user->id, 'name' => 'Celular antigo', 'credential_id' => 'credencial-antiga', 'credential' => '{}']);

        $this->withServerVariables(['HTTP_HOST' => 'host-nao-confiavel.teste'])
            ->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'email'])
            ->assertOk()->assertJsonPath('validade_minutos', 60);

        $mail = Mail::sent(RecuperacaoSenhaMail::class)->sole();
        $this->assertTrue($mail->hasTo($user->email));
        $this->assertStringStartsWith('https://condominio.teste/redefinir-senha/', $mail->link);
        $token = basename(parse_url($mail->link, PHP_URL_PATH));
        parse_str(parse_url($mail->link, PHP_URL_QUERY), $query);
        $this->assertSame($user->login, $query['login']);
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
        $mail->assertSeeInHtml('Criar nova senha');

        $dados = ['login' => $user->login, 'token' => $token, 'password' => 'novaSenha123', 'password_confirmation' => 'novaSenha123'];
        $this->post('/redefinir-senha', $dados)->assertRedirect('/login');

        $this->assertTrue(Hash::check('novaSenha123', $user->refresh()->password));
        $this->assertFalse($user->deve_trocar_senha);
        $this->assertNotNull($user->senha_definida_em);
        $this->assertSame($segredo, $user->two_factor_secret);
        $this->assertTrue($user->temDoisFatores());
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-antiga']);
        $this->assertDatabaseMissing('passkeys', ['credential_id' => 'credencial-antiga']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertGuest();

        $this->from('/redefinir-senha/'.$token)->post('/redefinir-senha', [...$dados, 'password' => 'outraSenha123', 'password_confirmation' => 'outraSenha123'])
            ->assertSessionHas('erro');
        $this->assertTrue(Hash::check('novaSenha123', $user->refresh()->password));
        $this->assertTrue(app(Auditoria::class)->verificar()['integra']);
    }

    public function test_whatsapp_envia_pelo_gzappy_e_funciona_para_unidade_sem_email(): void
    {
        $this->predio();
        $user = $this->morador('302');
        $user->update(['telefone' => '5583999991492']);
        Http::fake(['https://v2-api.gzappy.com/message/send-text' => Http::response(['success' => true])]);

        $this->postJson('/recuperar-senha/enviar', ['identificador' => '302', 'canal' => 'whatsapp'])
            ->assertOk()->assertJsonPath('destino', '(83) •••••-1492');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer chave-teste')
            && $request['phone'] === ['5583999991492']
            && str_contains($request['message'], 'https://condominio.teste/redefinir-senha/')
            && str_contains($request['message'], 'login=302'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => '302']);
        Mail::assertNothingSent();
    }

    public function test_falha_do_gzappy_invalida_token_e_permite_tentar_email(): void
    {
        $user = User::factory()->create(['telefone' => '83999991492']);
        Http::fake(['*' => Http::response(['message' => 'Detalhe privado do provedor'], 500)]);

        $resposta = $this->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'whatsapp'])->assertUnprocessable();
        $this->assertStringNotContainsString('Detalhe privado', $resposta->getContent());
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'email'])->assertOk();
        Mail::assertSent(RecuperacaoSenhaMail::class, 1);
    }

    public function test_whatsapp_sem_chave_nao_faz_requisicao(): void
    {
        config(['services.gzappy.api_key' => null]);
        $user = User::factory()->create(['telefone' => '83999991492']);

        $this->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'whatsapp'])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_falha_no_email_nao_mantem_token_nem_expoe_detalhes_internos(): void
    {
        $user = User::factory()->create();
        Mail::swap(\Mockery::mock(MailManager::class)->shouldReceive('to')->once()->andThrow(new \RuntimeException('Segredo do provedor'))->getMock());

        $resposta = $this->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'email'])->assertUnprocessable();

        $this->assertStringNotContainsString('Segredo do provedor', $resposta->getContent());
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_email_de_avisos_nao_autoriza_recuperacao_da_unidade(): void
    {
        $this->predio();
        $this->morador('302')->unidade->update(['email_avisos' => 'avisos@example.com']);
        $this->postJson('/recuperar-senha/canais', ['identificador' => '302'])->assertOk()->assertExactJson(['canais' => ['email' => null, 'whatsapp' => null]]);
    }

    public function test_reenvio_entre_canais_e_limitado_por_usuario(): void
    {
        $user = User::factory()->create(['telefone' => '83999991492']);
        $this->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'email'])->assertOk();
        $this->postJson('/recuperar-senha/enviar', ['identificador' => $user->login, 'canal' => 'whatsapp'])->assertUnprocessable();

        Mail::assertSent(RecuperacaoSenhaMail::class, 1);
        Http::assertNothingSent();
    }

    public function test_link_expirado_invalido_ou_de_outro_usuario_nao_altera_senha(): void
    {
        $user = User::factory()->create();
        $outro = User::factory()->create();
        $token = Password::createToken($user);
        $dados = ['login' => $user->login, 'token' => $token, 'password' => 'novaSenha123', 'password_confirmation' => 'novaSenha123'];

        $this->post('/redefinir-senha', [...$dados, 'token' => 'invalido'])->assertSessionHas('erro');
        $this->post('/redefinir-senha', [...$dados, 'login' => $outro->login])->assertSessionHas('erro');
        $this->travel(61)->minutes();
        $this->post('/redefinir-senha', $dados)->assertSessionHas('erro');
        $this->assertTrue(Hash::check('password', $user->refresh()->password));
        $this->assertTrue(Hash::check('password', $outro->refresh()->password));
    }

    public function test_contatos_exigem_senha_atual_normalizam_celular_e_revogam_link_anterior(): void
    {
        $user = User::factory()->create();
        Password::createToken($user);
        $dados = ['email_recuperacao' => 'recuperacao@example.com', 'telefone' => '+55 (83) 99999-1492', 'senha_atual' => 'errada'];

        $this->actingAs($user)->put('/conta/recuperacao', $dados)->assertSessionHasErrors('senha_atual');
        $this->assertNull($user->refresh()->telefone);
        $this->actingAs($user)->put('/conta/recuperacao', [...$dados, 'senha_atual' => 'password'])->assertSessionHas('sucesso');
        $this->assertSame('83999991492', $user->refresh()->telefone);
        $this->assertSame('recuperacao@example.com', $user->email_recuperacao);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->actingAs($user)->put('/conta/recuperacao', [...$dados, 'senha_atual' => 'password', 'telefone' => '123'])->assertSessionHasErrors('telefone');
    }

    public function test_redefinicao_exige_confirmacao_e_senha_forte(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post('/redefinir-senha', ['login' => $user->login, 'token' => $token, 'password' => '12345', 'password_confirmation' => 'diferente'])->assertSessionHasErrors('password');
        $this->assertTrue(Password::tokenExists($user, $token));
        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
