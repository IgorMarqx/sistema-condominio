<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cobranca_id
 * @property int $valor_centavos
 * @property Carbon $pago_em
 * @property string|null $comprovante_s3
 * @property string|null $sha256
 * @property string|null $comprovante_nome
 * @property string $status
 * @property string|null $motivo_recusa
 * @property int $enviado_por
 * @property string|null $nome_informado
 * @property int|null $confirmado_por
 * @property Carbon|null $confirmado_em
 * @property Carbon $created_at
 * @property-read Cobranca $cobranca
 */
#[Fillable([
    'cobranca_id', 'valor_centavos', 'pago_em', 'comprovante_s3', 'sha256', 'comprovante_nome', 'status',
    'motivo_recusa', 'enviado_por', 'nome_informado', 'confirmado_por', 'confirmado_em',
])]
class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'pago_em' => 'date',
            'confirmado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Cobranca, $this>
     */
    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
