<?php

use App\Jobs\BackupBanco;
use App\Jobs\FaturaAtrasada;
use App\Jobs\GerarCompetencias;
use App\Jobs\LembreteLeitura;
use App\Jobs\LembreteVencimento;
use Illuminate\Support\Facades\Schedule;

// Horários em America/Sao_Paulo; cada job é idempotente (UNIQUE da competência e chave do aviso).
Schedule::job(new BackupBanco)->dailyAt('03:00')->timezone('America/Sao_Paulo')->onOneServer();
Schedule::job(new GerarCompetencias)->dailyAt('06:00')->timezone('America/Sao_Paulo')->onOneServer();
Schedule::job(new LembreteLeitura)->dailyAt('08:00')->timezone('America/Sao_Paulo')->onOneServer();
Schedule::job(new FaturaAtrasada)->dailyAt('09:00')->timezone('America/Sao_Paulo')->onOneServer();
Schedule::job(new LembreteVencimento)->dailyAt('09:00')->timezone('America/Sao_Paulo')->onOneServer();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
