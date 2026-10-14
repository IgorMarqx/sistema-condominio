<?php

namespace Tests\Feature;

use App\Enums\Concessionaria;
use App\Jobs\FaturaAtrasada;
use App\Jobs\GerarCompetencias;
use App\Models\Competencia;
use App\Models\Notificacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JobsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_gerar_competencias_e_idempotente(): void
    {
        $this->predio();
        Carbon::setTestNow('2026-10-13 09:00:00');
        $this->medidor(null, Concessionaria::Energisa, '5/1');
        $this->medidor('302', Concessionaria::Cagepa, '777');

        dispatch_sync(new GerarCompetencias);
        dispatch_sync(new GerarCompetencias);

        $this->assertSame(2, Competencia::query()->where('mes_ref', '2026-10-01')->count());
    }

    public function test_fatura_atrasada_avisa_uma_vez(): void
    {
        $this->predio();
        Carbon::setTestNow('2026-10-20 12:00:00');
        $medidor = $this->medidor(null, Concessionaria::Energisa, '5/1');
        Competencia::query()->create(['medidor_id' => $medidor->id, 'mes_ref' => '2026-10-01', 'leitura_prevista' => '2026-10-10', 'status' => 'prevista']);

        dispatch_sync(new FaturaAtrasada);
        dispatch_sync(new FaturaAtrasada);

        $this->assertSame(1, Notificacao::query()->where('tipo', 'fatura_atrasada')->count());
    }
}
