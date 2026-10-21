<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Base do prédio (12 unidades, contas do caixa, endereço do condomínio). Em ambiente local,
     * também o cenário de demonstração dos mockups.
     */
    public function run(): void
    {
        Artisan::call('condominio:instalar', ['--no-interaction' => true]);

        $this->call(SindicoSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
