<?php

namespace App\Console\Commands;

use App\Services\Ingestao\RecebedorEmail;
use Illuminate\Console\Command;

/**
 * Injeta um .eml como se tivesse chegado pelo servidor de entrada (testes e faturas antigas).
 */
class ImportarEml extends Command
{
    protected $signature = 'faturas:importar-eml {arquivo} {--para= : endereço de destino (sem isso, usa o To: do e-mail)}';

    protected $description = 'Processa um .eml como e-mail recebido';

    public function handle(RecebedorEmail $recebedor): int
    {
        $arquivo = $this->argument('arquivo');

        if (! is_string($arquivo)) {
            return self::FAILURE;
        }

        if (! is_file($arquivo)) {
            $this->error('Arquivo não encontrado.');

            return self::FAILURE;
        }

        $email = $recebedor->receber((string) file_get_contents($arquivo), array_filter([is_string($this->option('para')) ? $this->option('para') : '']));

        if (! $email) {
            $this->warn('Descartado: nenhum destinatário é uma caixa de entrada ativa.');

            return self::FAILURE;
        }

        $email->refresh();
        $this->info('E-mail #'.$email->id.' → '.$email->status.($email->erro ? ' ('.$email->erro.')' : ''));

        return self::SUCCESS;
    }
}
