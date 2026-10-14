<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medidor_id')->constrained('medidores');
            // Primeiro dia do mês de referência.
            $table->date('mes_ref');
            $table->date('leitura_prevista')->nullable();
            $table->enum('leitura_prevista_origem', ['fatura', 'estimada', 'manual'])->nullable();
            $table->date('leitura_realizada')->nullable();
            $table->string('leitura_foto_s3')->nullable();
            $table->enum('status', ['prevista', 'leitura_realizada', 'fatura_recebida', 'confirmada', 'rateada', 'quitada'])
                ->default('prevista');
            $table->timestamps();

            $table->unique(['medidor_id', 'mes_ref']);
            $table->index(['status', 'leitura_prevista']);
        });

        Schema::create('faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competencia_id')->constrained('competencias');
            $table->foreignId('medidor_id')->constrained('medidores');
            $table->foreignId('anexo_id')->nullable()->constrained('anexos');
            $table->foreignId('email_id')->nullable()->constrained('emails_recebidos');
            $table->enum('origem', ['email', 'upload']);
            $table->unsignedBigInteger('valor_centavos')->nullable();
            $table->date('vencimento')->nullable();
            $table->date('emissao')->nullable();
            $table->decimal('consumo', 12, 3)->nullable();
            $table->string('unidade_medida', 6)->nullable();
            $table->date('leitura_anterior')->nullable();
            $table->date('leitura_atual')->nullable();
            $table->date('proxima_leitura')->nullable();
            $table->char('chave_nf3e', 44)->nullable()->unique();
            $table->string('linha_digitavel', 80)->nullable();
            $table->text('pix_copia_cola')->nullable();
            $table->json('itens_json')->nullable();
            $table->longText('texto_extraido')->nullable();
            $table->string('extrator', 30)->nullable();
            $table->string('extrator_versao', 20)->nullable();
            $table->decimal('confianca', 4, 3)->nullable();
            $table->json('validacoes_json')->nullable();
            $table->enum('status', ['pendente_revisao', 'pendente_confirmacao', 'processada', 'confirmada', 'rejeitada']);
            $table->text('motivo_revisao')->nullable();
            $table->foreignId('confirmada_por')->nullable()->constrained('users');
            $table->timestamp('confirmada_em')->nullable();
            // Conta individual: a unidade informa que pagou.
            $table->date('paga_em')->nullable();
            $table->timestamps();

            $table->index(['medidor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
        Schema::dropIfExists('competencias');
    }
};
