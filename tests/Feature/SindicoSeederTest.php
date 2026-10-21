<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auditoria\Auditoria;
use Database\Seeders\SindicoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SindicoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_sindico_com_senha_hasheada_e_auditoria(): void
    {
        $this->seed(SindicoSeeder::class);

        $sindico = User::query()->where('login', 'igormarquesdeazevedo11@gmail.com')->firstOrFail();

        $this->assertSame('Igor Marques', $sindico->nome);
        $this->assertSame($sindico->login, $sindico->email);
        $this->assertSame('12489723413', $sindico->cpf);
        $this->assertTrue($sindico->isAdmin());
        $this->assertTrue($sindico->ativo);
        $this->assertTrue($sindico->deve_trocar_senha);
        $this->assertTrue(Hash::check('12345', $sindico->password));
        $this->assertFalse($sindico->temDoisFatores());
        $this->assertDatabaseHas('audit_log', ['acao' => 'acesso.admin_criado', 'entidade_id' => $sindico->id]);
        $this->assertTrue(app(Auditoria::class)->verificar()['integra']);

        $this->post('/login', ['login' => $sindico->login, 'password' => '12345'])->assertRedirect();
        $this->assertAuthenticatedAs($sindico);
        $this->get('/gestao')->assertRedirect('/trocar-senha');
    }

    public function test_reexecutar_preserva_senha_e_dois_fatores_sem_duplicar_usuario_ou_auditoria(): void
    {
        $this->seed(SindicoSeeder::class);
        $sindico = User::query()->where('login', 'igormarquesdeazevedo11@gmail.com')->firstOrFail();
        $sindico->forceFill([
            'password' => 'novaSenha123',
            'deve_trocar_senha' => false,
            'two_factor_secret' => encrypt('segredo-configurado'),
            'two_factor_recovery_codes' => encrypt('["codigo-configurado"]'),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $antes = $sindico->refresh()->getAttributes();

        $this->seed(SindicoSeeder::class);

        $this->assertSame($antes, $sindico->refresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_log', 1);
    }
}
