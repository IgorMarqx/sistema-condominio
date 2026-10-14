<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidades e acessos. Cada apartamento tem um login compartilhado (usuário = número do
     * apartamento); o síndico e o conselho têm login nominal pelo e-mail.
     */
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 6)->unique();
            $table->unsignedTinyInteger('andar');
            $table->decimal('fracao_ideal', 7, 4)->nullable();
            $table->boolean('ativa')->default(true);
            // LGPD: o síndico só vê valor e consumo das contas individuais com autorização da unidade.
            $table->boolean('autoriza_admin_ver_faturas')->default(false);
            $table->string('email_avisos')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('login', 120)->unique();
            $table->string('email')->nullable()->unique();
            $table->enum('tipo', ['admin', 'unidade', 'conselho']);
            $table->foreignId('unidade_id')->nullable()->unique()->constrained('unidades');
            $table->string('password');
            $table->boolean('deve_trocar_senha')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamp('senha_definida_em')->nullable();
            $table->timestamp('ultimo_acesso_em')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Quem acessou: com login compartilhado, o nome informado é o que identifica a pessoa.
        Schema::create('sessoes_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('unidade_id')->nullable()->constrained('unidades');
            $table->string('nome_informado', 60)->nullable();
            $table->enum('metodo', ['senha', 'biometria']);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('criado_em')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessoes_log');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('unidades');
    }
};
