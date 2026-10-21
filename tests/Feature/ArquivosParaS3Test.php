<?php

namespace Tests\Feature;

use App\Enums\CategoriaLancamento;
use App\Models\ContaCaixa;
use App\Services\Caixa\CaixaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArquivosParaS3Test extends TestCase
{
    use RefreshDatabase;

    public function test_copia_para_o_s3_os_anexos_que_estao_no_disco_local(): void
    {
        $this->predio();
        Storage::fake('s3');
        $admin = $this->sindico();

        $lancamento = app(CaixaService::class)->lancar(
            ContaCaixa::corrente(), Carbon::today(), 'entrada', 1000, CategoriaLancamento::Outros, 'Saldo', $admin,
            UploadedFile::fake()->createWithContent('extrato.pdf', '%PDF-1.4 extrato'),
        );

        $this->artisan('condominio:arquivos-para-s3', ['--dry-run' => true])->assertSuccessful();
        Storage::disk('s3')->assertMissing($lancamento->comprovante_s3);

        $this->artisan('condominio:arquivos-para-s3')->assertSuccessful();
        Storage::disk('s3')->assertExists($lancamento->comprovante_s3);
        $this->assertSame('%PDF-1.4 extrato', Storage::disk('s3')->get($lancamento->comprovante_s3));
        Storage::disk('local')->assertExists($lancamento->comprovante_s3);

        // Segunda execução não copia de novo; arquivo sumido dos dois lados é avisado.
        $this->artisan('condominio:arquivos-para-s3')->expectsOutputToContain('Copiados: 0 de 1')->assertSuccessful();
        Storage::disk('local')->delete($lancamento->comprovante_s3);
        Storage::disk('s3')->delete($lancamento->comprovante_s3);
        $this->artisan('condominio:arquivos-para-s3')->assertFailed();
    }

    public function test_bucket_configurado_leva_os_anexos_para_o_s3(): void
    {
        $this->assertSame('local', config('condominio.disco'));

        $configuracao = require config_path('condominio.php');
        $this->assertSame('local', $configuracao['disco']);

        putenv('AWS_BUCKET=condominio-anexos');
        putenv('CONDOMINIO_DISCO');
        $_ENV['AWS_BUCKET'] = $_SERVER['AWS_BUCKET'] = 'condominio-anexos';
        unset($_ENV['CONDOMINIO_DISCO'], $_SERVER['CONDOMINIO_DISCO']);

        try {
            $configuracao = require config_path('condominio.php');
            $this->assertSame('s3', $configuracao['disco']);
            $this->assertSame('s3', $configuracao['disco_backup']);
        } finally {
            putenv('AWS_BUCKET');
            unset($_ENV['AWS_BUCKET'], $_SERVER['AWS_BUCKET']);
            putenv('CONDOMINIO_DISCO=local');
            $_ENV['CONDOMINIO_DISCO'] = $_SERVER['CONDOMINIO_DISCO'] = 'local';
        }
    }
}
