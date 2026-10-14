<?php

namespace App\Models;

use App\Enums\StatusCobranca;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rateio_id
 * @property int $unidade_id
 * @property Carbon $mes_ref
 * @property int $valor_centavos
 * @property Carbon $vencimento
 * @property StatusCobranca $status
 * @property string|null $pix_txid
 * @property-read Rateio $rateio
 * @property-read Unidade $unidade
 */
#[Fillable(['rateio_id', 'unidade_id', 'mes_ref', 'valor_centavos', 'vencimento', 'status', 'pix_txid'])]
class Cobranca extends Model
{
    protected $table = 'cobrancas';

    protected function casts(): array
    {
        return [
            'mes_ref' => 'date',
            'vencimento' => 'date',
            'valor_centavos' => 'integer',
            'status' => StatusCobranca::class,
        ];
    }

    /**
     * @return BelongsTo<Rateio, $this>
     */
    public function rateio(): BelongsTo
    {
        return $this->belongsTo(Rateio::class);
    }

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * @return HasMany<CobrancaItem, $this>
     */
    public function itens(): HasMany
    {
        return $this->hasMany(CobrancaItem::class);
    }

    /**
     * @return HasMany<Pagamento, $this>
     */
    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function vencida(): bool
    {
        return $this->status === StatusCobranca::Aberta && $this->vencimento->lt(today());
    }
}
