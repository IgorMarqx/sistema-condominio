<?php

namespace App\Console\Commands;

use App\Models\Unidade;
use App\Models\User;
use App\Services\Auditoria\Ator;
use App\Services\Auditoria\Auditoria;
use App\Support\SenhaAleatoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Senha inicial (16 caracteres) de cada apartamento, para entregar em papel. Só cria para
 * quem ainda não tem acesso, a menos que --todas.
 */
class GerarSenhasUnidades extends Command
{
    protected $signature = 'condominio:senhas-unidades {--todas : redefine também quem já tem acesso}';

    protected $description = 'Gera as senhas iniciais dos apartamentos (troca obrigatória no primeiro acesso)';

    public function handle(Auditoria $auditoria): int
    {
        $linhas = [];

        foreach (Unidade::query()->where('ativa', true)->ordemFixa()->with('acesso')->get() as $unidade) {
            if ($unidade->acesso && ! $this->option('todas')) {
                continue;
            }

            $senha = SenhaAleatoria::gerar();

            DB::transaction(function () use ($unidade, $senha, $auditoria) {
                $user = $unidade->acesso ?? new User([
                    'nome' => 'Apartamento '.$unidade->numero,
                    'login' => $unidade->numero,
                    'tipo' => 'unidade',
                    'unidade_id' => $unidade->id,
                ]);

                $user->forceFill(['password' => $senha, 'deve_trocar_senha' => true, 'ativo' => true, 'senha_definida_em' => now()])->save();
                $auditoria->registrar('acesso.senha_redefinida', $user, null, ['unidade' => $unidade->numero], Ator::sistema());
            });

            $linhas[] = [$unidade->numero, $senha];
        }

        if ($linhas === []) {
            $this->info('Todas as unidades já têm acesso. Use --todas para redefinir.');

            return self::SUCCESS;
        }

        $this->table(['Apartamento', 'Senha inicial'], $linhas);
        $this->warn('Entregue em papel. As senhas não ficam guardadas em lugar nenhum.');

        return self::SUCCESS;
    }
}
