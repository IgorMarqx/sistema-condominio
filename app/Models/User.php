<?php

namespace App\Models;

use App\Enums\TipoUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Acesso ao sistema. A unidade tem login compartilhado (login = número do apartamento);
 * síndico e conselho entram pelo e-mail, com 2FA obrigatório para o síndico.
 *
 * @property int $id
 * @property string $nome
 * @property string $login
 * @property string|null $email
 * @property TipoUsuario $tipo
 * @property int|null $unidade_id
 * @property string $password
 * @property bool $deve_trocar_senha
 * @property bool $ativo
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $senha_definida_em
 * @property Carbon|null $ultimo_acesso_em
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unidade|null $unidade
 */
#[Fillable(['nome', 'login', 'email', 'tipo', 'unidade_id', 'password', 'deve_trocar_senha', 'ativo', 'senha_definida_em'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'tipo' => TipoUsuario::class,
            'password' => 'hashed',
            'deve_trocar_senha' => 'boolean',
            'ativo' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'senha_definida_em' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function isAdmin(): bool
    {
        return $this->tipo === TipoUsuario::Admin;
    }

    public function isUnidade(): bool
    {
        return $this->tipo === TipoUsuario::Unidade;
    }

    public function isConselho(): bool
    {
        return $this->tipo === TipoUsuario::Conselho;
    }

    /**
     * Síndico e conselho enxergam a área de gestão; só o síndico altera.
     */
    public function vePainelGestao(): bool
    {
        return $this->isAdmin() || $this->isConselho();
    }

    public function temDoisFatores(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function getPasskeyUsername(): string
    {
        return $this->login;
    }

    public function getPasskeyDisplayName(): string
    {
        return $this->isUnidade() ? 'Apartamento '.$this->login : $this->nome;
    }
}
