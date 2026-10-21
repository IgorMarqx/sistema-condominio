<?php

namespace App\Console\Commands;

use App\Models\ContaCaixa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Auditoria\Ator;
use App\Services\Auditoria\Auditoria;
use App\Support\SenhaAleatoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prepara o prédio: 12 unidades (101–403), contas do caixa, endereço de faturas do condomínio
 * e o acesso do síndico. Idempotente.
 */
class InstalarCondominio extends Command
{
    protected $signature = 'condominio:instalar
        {--andares=4 : quantidade de andares}
        {--por-andar=3 : apartamentos por andar}
        {--admin-email= : e-mail do síndico}
        {--admin-nome= : nome do síndico}';

    protected $description = 'Cria unidades, contas do caixa e o acesso do síndico';

    public function handle(Auditoria $auditoria): int
    {
        DB::transaction(function () use ($auditoria) {
            for ($andar = 1; $andar <= (int) $this->option('andares'); $andar++) {
                for ($ap = 1; $ap <= (int) $this->option('por-andar'); $ap++) {
                    $numero = $andar.str_pad((string) $ap, 2, '0', STR_PAD_LEFT);
                    $unidade = Unidade::query()->firstOrCreate(['numero' => $numero], ['andar' => $andar, 'ativa' => true]);

                    if ($unidade->wasRecentlyCreated) {
                        $auditoria->registrar('unidade.criada', $unidade, null, ['numero' => $numero], Ator::sistema());
                    }
                }
            }

            ContaCaixa::query()->firstOrCreate(['slug' => 'corrente'], ['nome' => 'Conta corrente']);
            ContaCaixa::query()->firstOrCreate(['slug' => 'reserva'], ['nome' => 'Fundo de reserva']);
        });

        $this->info('Unidades: '.Unidade::query()->count());

        $email = $this->option('admin-email') ?: $this->ask('E-mail do síndico (vazio para pular)');

        if ($email) {
            $nome = $this->option('admin-nome') ?: $this->ask('Nome do síndico', 'Síndico');
            $senha = SenhaAleatoria::gerar();

            $admin = User::query()->firstOrNew(['login' => strtolower($email)]);
            $novo = ! $admin->exists;
            $admin->forceFill([
                'nome' => $nome,
                'email' => strtolower($email),
                'tipo' => 'admin',
                'ativo' => true,
                'password' => $senha,
                'deve_trocar_senha' => true,
            ])->save();

            $auditoria->registrar($novo ? 'acesso.admin_criado' : 'acesso.senha_redefinida', $admin, null, ['email' => $admin->email], Ator::sistema());

            $this->newLine();
            $this->warn('Acesso do síndico (mostrado só agora):');
            $this->line('  login: '.$admin->login);
            $this->line('  senha: '.$senha);
            $this->line('  No primeiro acesso: trocar a senha e ativar a verificação em duas etapas.');
        }

        return self::SUCCESS;
    }
}
