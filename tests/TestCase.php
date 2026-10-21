<?php

namespace Tests;

use App\Enums\Concessionaria;
use App\Models\Medidor;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Ingestao\DadosFatura;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prédio de 12 unidades e contas do caixa.
     */
    protected function predio(): void
    {
        Storage::fake('local');
        Artisan::call('condominio:instalar', ['--no-interaction' => true]);
    }

    protected function unidade(string $numero): Unidade
    {
        return Unidade::query()->where('numero', $numero)->firstOrFail();
    }

    protected function morador(string $numero): User
    {
        $unidade = $this->unidade($numero);

        return $unidade->acesso ?? User::factory()->daUnidade($unidade)->create();
    }

    protected function sindico(): User
    {
        return User::query()->where('tipo', 'admin')->first() ?? User::factory()->comDoisFatores()->create(['nome' => 'Síndico Teste']);
    }

    protected function medidor(?string $numero, Concessionaria $concessionaria, string $identificador): Medidor
    {
        return Medidor::query()->create([
            'dono_tipo' => $numero ? 'unidade' : 'condominio',
            'unidade_id' => $numero ? $this->unidade($numero)->id : null,
            'concessionaria' => $concessionaria,
            'identificador' => $identificador,
            'ativo' => true,
        ]);
    }

    protected function dados(Medidor $medidor, string $mes, int $valor, int $consumo = 100): DadosFatura
    {
        $ref = Carbon::parse($mes.'-01');

        return new DadosFatura(
            $medidor->concessionaria,
            identificador: $medidor->identificador,
            mesRef: $ref,
            valorCentavos: $valor,
            vencimento: $ref->copy()->day(22),
            emissao: $ref->copy()->day(12),
            consumo: (string) $consumo,
            leituraAnterior: $ref->copy()->subMonth()->day(10),
            leituraAtual: $ref->copy()->day(10),
            proximaLeitura: $ref->copy()->addMonth()->day(10),
            linhaDigitavel: '83640000001-8 86400033000-5 10012345678-2 02026101234-5',
            extrator: 'teste',
            versao: '0',
        );
    }

    /**
     * PDF de verdade com o texto dado (o parser lê de volta).
     */
    protected function pdf(string $texto): string
    {
        return Pdf::loadHTML('<pre style="font-family: DejaVu Sans Mono; font-size: 9px">'.e($texto).'</pre>')->output();
    }
}
