<?php

namespace Tests\Feature;

use App\Exceptions\RegistroImutavel;
use App\Models\AuditLog;
use App\Services\Auditoria\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadeia_integra_e_encadeada(): void
    {
        $auditoria = app(Auditoria::class);
        $a = $auditoria->registrar('teste.um', 'coisa', null, ['valor' => 1, 'texto' => 'água']);
        $b = $auditoria->registrar('teste.dois', 'coisa', ['z' => 1, 'a' => 2], null);

        $this->assertSame($a->hash, $b->hash_anterior);
        $this->assertTrue($auditoria->verificar()['integra']);
    }

    public function test_alteracao_direta_no_banco_quebra_a_cadeia(): void
    {
        $auditoria = app(Auditoria::class);
        $auditoria->registrar('teste.um', 'coisa', null, ['valor' => 1]);
        $alvo = $auditoria->registrar('teste.dois', 'coisa', null, ['valor' => 2]);
        $auditoria->registrar('teste.tres', 'coisa', null, ['valor' => 3]);

        DB::table('audit_log')->where('id', $alvo->id)->update(['depois_json' => '{"valor":200}']);

        $resultado = $auditoria->verificar();
        $this->assertFalse($resultado['integra']);
        $this->assertSame($alvo->id, $resultado['falha']['id']);
    }

    public function test_registro_apagado_e_detectado(): void
    {
        $auditoria = app(Auditoria::class);
        $auditoria->registrar('teste.um');
        $meio = $auditoria->registrar('teste.dois');
        $auditoria->registrar('teste.tres');

        DB::table('audit_log')->where('id', $meio->id)->delete();

        $this->assertFalse($auditoria->verificar()['integra']);
    }

    public function test_model_recusa_editar_e_apagar(): void
    {
        $registro = app(Auditoria::class)->registrar('teste.um');

        $this->expectException(RegistroImutavel::class);
        $registro->update(['acao' => 'outra']);
    }

    public function test_comando_de_verificacao(): void
    {
        app(Auditoria::class)->registrar('teste.um');

        $this->artisan('auditoria:verificar')->assertSuccessful();
        $this->assertSame(1, AuditLog::query()->count());
    }
}
