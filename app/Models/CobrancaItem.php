<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cobranca_id
 * @property string $tipo
 * @property string $descricao
 * @property int $valor_centavos
 * @property int|null $fatura_id
 */
#[Fillable(['cobranca_id', 'tipo', 'descricao', 'valor_centavos', 'fatura_id'])]
class CobrancaItem extends Model
{
    public $timestamps = false;

    protected $table = 'cobranca_itens';

    protected function casts(): array
    {
        return ['valor_centavos' => 'integer'];
    }

    /**
     * @return BelongsTo<Fatura, $this>
     */
    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }
}
