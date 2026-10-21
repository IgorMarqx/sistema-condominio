<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Internet do prédio como terceira conta do condomínio: lançada no painel, dividida igual entre as
 * unidades e cobrada à parte de energia e água.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE medidores MODIFY concessionaria ENUM('energisa', 'cagepa', 'internet') NOT NULL");
        DB::statement("ALTER TABLE cobranca_itens MODIFY tipo ENUM('energia', 'agua', 'internet', 'contribuicao', 'ajuste') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cobranca_itens MODIFY tipo ENUM('energia', 'agua', 'contribuicao', 'ajuste') NOT NULL");
        DB::statement("ALTER TABLE medidores MODIFY concessionaria ENUM('energisa', 'cagepa') NOT NULL");
    }
};
