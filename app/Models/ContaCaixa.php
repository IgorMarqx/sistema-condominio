<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $nome
 */
#[Fillable(['slug', 'nome'])]
class ContaCaixa extends Model
{
    public $timestamps = false;

    protected $table = 'contas_caixa';

    public static function corrente(): self
    {
        return self::where('slug', 'corrente')->firstOrFail();
    }

    public static function reserva(): self
    {
        return self::where('slug', 'reserva')->firstOrFail();
    }

    /**
     * @return HasMany<LancamentoCaixa, $this>
     */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoCaixa::class, 'conta_id');
    }
}
