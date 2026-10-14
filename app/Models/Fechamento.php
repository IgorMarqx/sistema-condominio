<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $mes_ref
 * @property int $saldo_calculado_centavos
 * @property int $saldo_banco_centavos
 * @property int $diferenca_centavos
 * @property array<string, array{nome: string, calculado: int, banco: int}> $contas_json
 * @property string|null $observacao
 * @property string|null $pdf_s3
 * @property string|null $pdf_sha256
 * @property string|null $hash_auditoria
 * @property int $fechado_por
 * @property Carbon $fechado_em
 */
#[Fillable([
    'mes_ref', 'saldo_calculado_centavos', 'saldo_banco_centavos', 'diferenca_centavos', 'contas_json',
    'observacao', 'pdf_s3', 'pdf_sha256', 'hash_auditoria', 'fechado_por', 'fechado_em',
])]
class Fechamento extends Model
{
    protected $table = 'fechamentos';

    protected function casts(): array
    {
        return [
            'mes_ref' => 'date',
            'saldo_calculado_centavos' => 'integer',
            'saldo_banco_centavos' => 'integer',
            'diferenca_centavos' => 'integer',
            'contas_json' => 'array',
            'fechado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    /**
     * Último dia travado pelo fechamento mais recente (null = nenhum mês fechado).
     */
    public static function travadoAte(): ?Carbon
    {
        $ultimo = self::query()->orderByDesc('mes_ref')->value('mes_ref');

        return $ultimo ? Carbon::parse($ultimo)->endOfMonth()->startOfDay() : null;
    }
}
