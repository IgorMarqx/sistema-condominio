<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rateios', function (Blueprint $table) {
            $table->id();
            $table->date('mes_ref')->unique();
            $table->enum('regra', ['igual', 'fracao']);
            $table->unsignedBigInteger('total_centavos');
            $table->unsignedBigInteger('contribuicao_centavos')->default(0);
            $table->unsignedInteger('resto_centavos')->default(0);
            $table->date('vencimento');
            // Memória do cálculo: valor de cada unidade, quem recebeu o centavo do resto.
            $table->json('detalhe_json')->nullable();
            $table->enum('status', ['rascunho', 'confirmado']);
            $table->foreignId('criado_por')->constrained('users');
            $table->foreignId('confirmado_por')->nullable()->constrained('users');
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('rateio_faturas', function (Blueprint $table) {
            $table->foreignId('rateio_id')->constrained('rateios')->cascadeOnDelete();
            // Uma fatura entra em um rateio só.
            $table->foreignId('fatura_id')->unique()->constrained('faturas');
            $table->primary(['rateio_id', 'fatura_id']);
        });

        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rateio_id')->constrained('rateios')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->date('mes_ref');
            $table->unsignedBigInteger('valor_centavos');
            $table->date('vencimento');
            $table->enum('status', ['aberta', 'aguardando_confirmacao', 'paga', 'cancelada'])->default('aberta');
            $table->string('pix_txid', 25)->nullable();
            $table->timestamps();

            $table->unique(['rateio_id', 'unidade_id']);
            $table->index(['unidade_id', 'status']);
        });

        Schema::create('cobranca_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_id')->constrained('cobrancas')->cascadeOnDelete();
            $table->enum('tipo', ['energia', 'agua', 'contribuicao', 'ajuste']);
            $table->string('descricao');
            $table->bigInteger('valor_centavos');
            $table->foreignId('fatura_id')->nullable()->constrained('faturas');
        });

        // Rateio confirmado não muda: correção vira crédito ou débito no rateio seguinte.
        Schema::create('ajustes_rateio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rateio_origem_id')->nullable()->constrained('rateios');
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->string('descricao');
            $table->bigInteger('valor_centavos');
            $table->text('motivo');
            $table->foreignId('cobranca_item_id')->nullable()->constrained('cobranca_itens')->nullOnDelete();
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();
        });

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_id')->constrained('cobrancas');
            $table->unsignedBigInteger('valor_centavos');
            $table->date('pago_em');
            $table->string('comprovante_s3');
            $table->char('sha256', 64);
            $table->string('comprovante_nome');
            $table->enum('status', ['aguardando', 'confirmado', 'recusado'])->default('aguardando');
            $table->text('motivo_recusa')->nullable();
            $table->foreignId('enviado_por')->constrained('users');
            $table->string('nome_informado', 60)->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('users');
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('ajustes_rateio');
        Schema::dropIfExists('cobranca_itens');
        Schema::dropIfExists('cobrancas');
        Schema::dropIfExists('rateio_faturas');
        Schema::dropIfExists('rateios');
    }
};
