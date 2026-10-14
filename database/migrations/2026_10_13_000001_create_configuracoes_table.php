<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->string('chave', 80)->primary();
            $table->json('valor')->nullable();
            $table->timestamps();
        });

        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->enum('destino', ['unidade', 'admin']);
            $table->foreignId('unidade_id')->nullable()->constrained('unidades');
            $table->string('tipo', 60);
            $table->string('titulo');
            $table->string('mensagem')->nullable();
            $table->string('url')->nullable();
            $table->json('payload')->nullable();
            // Evita o mesmo aviso duas vezes quando o job diário roda de novo.
            $table->string('chave_unica', 160)->nullable()->unique();
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index(['destino', 'unidade_id', 'lida_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
        Schema::dropIfExists('configuracoes');
    }
};
