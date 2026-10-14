<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $mes_ref
 * @property string $regra
 * @property int $total_centavos
 * @property int $contribuicao_centavos
 * @property int $resto_centavos
 * @property Carbon $vencimento
 * @property array<string, mixed>|null $detalhe_json
 * @property string $status
 * @property int $criado_por
 * @property int|null $confirmado_por
 * @property Carbon|null $confirmado_em
 */
#[Fillable([
    'mes_ref', 'regra', 'total_centavos', 'contribuicao_centavos', 'resto_centavos', 'vencimento',
    'detalhe_json', 'status', 'criado_por', 'confirmado_por', 'confirmado_em',
])]
class Rateio extends Model
{
    protected $table = 'rateios';

    protected function casts(): array
    {
        return [
            'mes_ref' => 'date',
            'vencimento' => 'date',
            'total_centavos' => 'integer',
            'contribuicao_centavos' => 'integer',
            'resto_centavos' => 'integer',
            'detalhe_json' => 'array',
            'confirmado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Fatura, $this>
     */
    public function faturas(): BelongsToMany
    {
        return $this->belongsToMany(Fatura::class, 'rateio_faturas');
    }

    /**
     * @return HasMany<Cobranca, $this>
     */
    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function isConfirmado(): bool
    {
        return $this->status === 'confirmado';
    }
}
