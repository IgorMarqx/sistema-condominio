<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_caixa', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 20)->unique();
            $table->string('nome');
        });

        // Livro-razão append-only: nada é editado nem apagado; erro se corrige com estorno.
        // O saldo é sempre a soma dos lançamentos.
        Schema::create('lancamentos_caixa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas_caixa');
            $table->date('data');
            $table->enum('tipo', ['entrada', 'saida']);
            $table->unsignedBigInteger('valor_centavos');
            $table->string('categoria', 40);
            $table->string('descricao');
            $table->string('comprovante_s3')->nullable();
            $table->char('comprovante_sha256', 64)->nullable();
            $table->string('origem_tipo', 30)->default('manual');
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->foreignId('estorno_de_id')->nullable()->unique()->constrained('lancamentos_caixa');
            $table->text('motivo')->nullable();
            // Transferência entre contas: os dois lançamentos dividem o mesmo grupo.
            $table->uuid('grupo')->nullable()->index();
            $table->foreignId('autor_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conta_id', 'data']);
            $table->index(['origem_tipo', 'origem_id']);
        });

        Schema::create('fechamentos', function (Blueprint $table) {
            $table->id();
            $table->date('mes_ref')->unique();
            $table->bigInteger('saldo_calculado_centavos');
            $table->bigInteger('saldo_banco_centavos');
            $table->bigInteger('diferenca_centavos');
            $table->json('contas_json');
            $table->text('observacao')->nullable();
            $table->string('pdf_s3')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->char('hash_auditoria', 64)->nullable();
            $table->foreignId('fechado_por')->constrained('users');
            $table->timestamp('fechado_em');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos');
        Schema::dropIfExists('lancamentos_caixa');
        Schema::dropIfExists('contas_caixa');
    }
};
