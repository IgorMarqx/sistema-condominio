<?php

namespace App\Console\Commands;

use App\Services\Auditoria\Auditoria;
use Illuminate\Console\Command;

class VerificarAuditoria extends Command
{
    protected $signature = 'auditoria:verificar';

    protected $description = 'Recalcula a cadeia de hashes da auditoria';

    public function handle(Auditoria $auditoria): int
    {
        $resultado = $auditoria->verificar();

        if ($resultado['integra']) {
            $this->info('Cadeia íntegra: '.$resultado['total'].' registros. Último hash: '.$resultado['ultimo_hash']);

            return self::SUCCESS;
        }

        $this->error('Cadeia quebrada no registro #'.$resultado['falha']['id'].': '.$resultado['falha']['motivo']);

        return self::FAILURE;
    }
}
