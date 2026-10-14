<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $numero
 * @property int $andar
 * @property string|null $fracao_ideal
 * @property bool $ativa
 * @property bool $autoriza_admin_ver_faturas
 * @property string|null $email_avisos
 * @property-read User|null $acesso
 */
#[Fillable(['numero', 'andar', 'fracao_ideal', 'ativa', 'autoriza_admin_ver_faturas', 'email_avisos'])]
class Unidade extends Model
{
    protected $table = 'unidades';

    protected function casts(): array
    {
        return [
            'andar' => 'integer',
            'fracao_ideal' => 'decimal:4',
            'ativa' => 'boolean',
            'autoriza_admin_ver_faturas' => 'boolean',
        ];
    }

    /**
     * @return HasOne<User, $this>
     */
    public function acesso(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<Medidor, $this>
     */
    public function medidores(): HasMany
    {
        return $this->hasMany(Medidor::class);
    }

    /**
     * @return HasMany<Cobranca, $this>
     */
    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    /**
     * @return HasMany<CaixaEntrada, $this>
     */
    public function caixasEntrada(): HasMany
    {
        return $this->hasMany(CaixaEntrada::class);
    }

    /**
     * Ordem fixa usada para distribuir o resto dos centavos do rateio: 101, 102, …, 403.
     *
     * @param  Builder<Unidade>  $query
     */
    public function scopeOrdemFixa($query): void
    {
        $query->orderBy('andar')->orderBy('numero');
    }
}
