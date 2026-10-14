<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Diário, 03:00. mysqldump comprimido e cifrado com a chave da aplicação, no disco de backups;
 * apaga os com mais de 90 dias.
 */
class BackupBanco implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function handle(): void
    {
        $conexao = config('database.connections.'.config('database.default'));

        if (($conexao['driver'] ?? null) !== 'mysql') {
            return;
        }

        $processo = new Process([
            'mysqldump', '--single-transaction', '--routines', '--no-tablespaces',
            '-h', $conexao['host'], '-P', (string) $conexao['port'], '-u', $conexao['username'], $conexao['database'],
        ], env: ['MYSQL_PWD' => (string) $conexao['password']]);

        $processo->setTimeout(800)->run();

        if (! $processo->isSuccessful()) {
            throw new RuntimeException('mysqldump falhou: '.$processo->getErrorOutput());
        }

        $disco = Storage::disk(config('condominio.disco_backup', config('condominio.disco')));
        $disco->put('backups/'.now()->format('Y-m-d-His').'.sql.gz.enc', Crypt::encryptString((string) gzencode($processo->getOutput(), 9)));

        foreach ($disco->files('backups') as $arquivo) {
            if ($disco->lastModified($arquivo) < now()->subDays(90)->getTimestamp()) {
                $disco->delete($arquivo);
            }
        }
    }
}
