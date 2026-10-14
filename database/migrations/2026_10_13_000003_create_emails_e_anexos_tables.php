<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails_recebidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caixa_entrada_id')->nullable()->constrained('caixas_entrada');
            $table->string('destinatario');
            $table->string('remetente')->nullable();
            $table->string('assunto', 500)->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('s3_eml');
            $table->unsignedInteger('tamanho')->default(0);
            $table->string('spf', 20)->nullable();
            $table->string('dkim', 20)->nullable();
            $table->enum('tipo', ['fatura', 'verificacao', 'sem_anexo', 'outro'])->nullable();
            $table->enum('status', ['recebido', 'processado', 'duplicado', 'descartado', 'revisao', 'erro'])->default('recebido');
            $table->text('erro')->nullable();
            // E-mail de confirmação do Gmail/concessionária: código e link mostrados na tela da unidade.
            $table->string('verificacao_codigo', 40)->nullable();
            $table->text('verificacao_link')->nullable();
            // Links de fatura achados no corpo: nunca seguidos automaticamente.
            $table->json('links')->nullable();
            $table->timestamp('recebido_em');
            $table->timestamp('processado_em')->nullable();
            $table->timestamps();

            $table->index(['caixa_entrada_id', 'tipo', 'recebido_em']);
        });

        Schema::create('anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->nullable()->constrained('emails_recebidos');
            $table->string('nome');
            $table->string('s3_key');
            $table->char('sha256', 64)->unique();
            $table->unsignedInteger('tamanho');
            $table->string('mime', 100)->default('application/pdf');
            $table->foreignId('enviado_por')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anexos');
        Schema::dropIfExists('emails_recebidos');
    }
};
