<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Endereço exclusivo de recebimento de faturas, do condomínio ou de uma unidade.
        Schema::create('caixas_entrada', function (Blueprint $table) {
            $table->id();
            $table->enum('dono_tipo', ['condominio', 'unidade']);
            $table->foreignId('unidade_id')->nullable()->constrained('unidades');
            $table->string('endereco')->unique();
            $table->boolean('ativo')->default(true);
            $table->timestamp('desativado_em')->nullable();
            $table->timestamps();

            $table->index(['dono_tipo', 'unidade_id', 'ativo']);
        });

        Schema::create('medidores', function (Blueprint $table) {
            $table->id();
            $table->enum('dono_tipo', ['condominio', 'unidade']);
            $table->foreignId('unidade_id')->nullable()->constrained('unidades');
            $table->enum('concessionaria', ['energisa', 'cagepa']);
            // Nº da unidade consumidora (Energisa) ou matrícula (Cagepa).
            $table->string('identificador', 40);
            $table->string('descricao')->nullable();
            $table->text('senha_pdf')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['concessionaria', 'identificador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medidores');
        Schema::dropIfExists('caixas_entrada');
    }
};
