<?php

namespace Database\Seeders;

use App\Enums\TipoUsuario;
use App\Models\User;
use App\Services\Auditoria\Ator;
use App\Services\Auditoria\Auditoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SindicoSeeder extends Seeder
{
    public function run(Auditoria $auditoria): void
    {
        DB::transaction(function () use ($auditoria) {
            $sindico = User::query()->firstOrCreate(['login' => 'igormarquesdeazevedo11@gmail.com'], [
                'nome' => 'Igor Marques',
                'email' => 'igormarquesdeazevedo11@gmail.com',
                'cpf' => '12489723413',
                'tipo' => TipoUsuario::Admin,
                'password' => '12345',
                'deve_trocar_senha' => true,
                'ativo' => true,
            ]);

            if ($sindico->wasRecentlyCreated) {
                $auditoria->registrar('acesso.admin_criado', $sindico, null, ['email' => $sindico->email], Ator::sistema());
            }
        });
    }
}
