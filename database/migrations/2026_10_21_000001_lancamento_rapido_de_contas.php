<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conta lançada no painel: cada conta do condomínio (Energisa, Cagepa) gera o próprio rateio,
 * então o mês passa a ter mais de um. A conta digitada (valor e consumo) é origem "manual", e o
 * recebimento marcado pelo síndico no painel pode não ter comprovante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rateios', function (Blueprint $table) {
            $table->dropUnique(['mes_ref']);
            $table->index('mes_ref');
        });

        DB::statement("ALTER TABLE faturas MODIFY origem ENUM('email', 'upload', 'manual') NOT NULL");

        Schema::table('pagamentos', function (Blueprint $table) {
            $table->string('comprovante_s3')->nullable()->change();
            $table->char('sha256', 64)->nullable()->change();
            $table->string('comprovante_nome')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->string('comprovante_s3')->nullable(false)->change();
            $table->char('sha256', 64)->nullable(false)->change();
            $table->string('comprovante_nome')->nullable(false)->change();
        });

        DB::statement("ALTER TABLE faturas MODIFY origem ENUM('email', 'upload') NOT NULL");

        Schema::table('rateios', function (Blueprint $table) {
            $table->dropIndex(['mes_ref']);
            $table->unique('mes_ref');
        });
    }
};
