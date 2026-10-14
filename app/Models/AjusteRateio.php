<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Crédito (negativo) ou débito (positivo) que entra no próximo rateio de uma unidade.
 *
 * @property int $id
 * @property int|null $rateio_origem_id
 * @property int $unidade_id
 * @property string $descricao
 * @property int $valor_centavos
 * @property string $motivo
 * @property int|null $cobranca_item_id
 * @property-read Unidade $unidade
 */
#[Fillable(['rateio_origem_id', 'unidade_id', 'descricao', 'valor_centavos', 'motivo', 'cobranca_item_id', 'criado_por'])]
class AjusteRateio extends Model
{
    protected $table = 'ajustes_rateio';

    protected function casts(): array
    {
        return ['valor_centavos' => 'integer'];
    }

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
