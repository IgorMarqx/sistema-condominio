<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only e encadeado: hash = SHA-256(hash_anterior + conteúdo).
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->enum('ator_tipo', ['admin', 'unidade', 'conselho', 'sistema']);
            $table->unsignedBigInteger('ator_id')->nullable();
            $table->string('ator_rotulo', 80)->nullable();
            $table->string('nome_informado', 60)->nullable();
            $table->string('acao', 60);
            $table->string('entidade', 40)->nullable();
            $table->unsignedBigInteger('entidade_id')->nullable();
            // Texto (não JSON nativo): o MySQL reordena as chaves do tipo JSON e o hash quebraria.
            $table->longText('antes_json')->nullable();
            $table->longText('depois_json')->nullable();
            $table->string('ip', 45)->nullable();
            $table->char('hash_anterior', 64)->nullable();
            $table->char('hash', 64)->unique();
            // Texto exato usado no hash, para a verificação não depender da precisão do TIMESTAMP.
            $table->string('registrado_em', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidade', 'entidade_id']);
            $table->index('acao');
        });

        // Cabeça da cadeia: uma linha só, travada com FOR UPDATE até o commit da transação de
        // quem registra. Dois registros simultâneos nunca leem o mesmo hash anterior.
        Schema::create('audit_cabeca', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('ultimo_id')->nullable();
            $table->char('ultimo_hash', 64)->nullable();
        });

        DB::table('audit_cabeca')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_cabeca');
        Schema::dropIfExists('audit_log');
    }
};
