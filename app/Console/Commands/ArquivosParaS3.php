<?php

namespace App\Console\Commands;

use App\Models\Anexo;
use App\Models\Competencia;
use App\Models\EmailRecebido;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Models\Pagamento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Leva para o S3 os anexos gravados no disco local antes de o bucket ser configurado. Copia só
 * o que o banco referencia, não apaga nada do disco local e pula o que já está no bucket.
 */
class ArquivosParaS3 extends Command
{
    protected $signature = 'condominio:arquivos-para-s3 {--dry-run : Só lista o que seria copiado}';

    protected $description = 'Copia para o S3 os anexos que ainda estão no disco local';

    public function handle(): int
    {
        $local = Storage::disk('local');
        $s3 = Storage::disk('s3');

        $caminhos = collect()
            ->merge(Anexo::query()->pluck('s3_key'))
            ->merge(Pagamento::query()->whereNotNull('comprovante_s3')->pluck('comprovante_s3'))
            ->merge(LancamentoCaixa::query()->whereNotNull('comprovante_s3')->pluck('comprovante_s3'))
            ->merge(Fechamento::query()->whereNotNull('pdf_s3')->pluck('pdf_s3'))
            ->merge(EmailRecebido::query()->pluck('s3_eml'))
            ->merge(Competencia::query()->whereNotNull('leitura_foto_s3')->pluck('leitura_foto_s3'))
            ->filter()->unique()->values();

        $copiados = 0;
        $faltando = [];

        foreach ($caminhos as $caminho) {
            if ($s3->exists($caminho)) {
                continue;
            }

            if (! $local->exists($caminho)) {
                $faltando[] = $caminho;

                continue;
            }

            if (! $this->option('dry-run')) {
                $s3->writeStream($caminho, $local->readStream($caminho));
            }

            $this->line(($this->option('dry-run') ? 'copiaria ' : 'copiado ').$caminho);
            $copiados++;
        }

        $this->info(($this->option('dry-run') ? 'Seriam copiados: ' : 'Copiados: ').$copiados.' de '.$caminhos->count().' arquivo(s).');

        foreach ($faltando as $caminho) {
            $this->warn('Não está nem no disco local nem no S3: '.$caminho);
        }

        return $faltando === [] ? self::SUCCESS : self::FAILURE;
    }
}
