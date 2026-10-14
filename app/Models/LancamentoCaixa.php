<?php

namespace App\Models;

use App\Enums\CategoriaLancamento;
use App\Exceptions\RegistroImutavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Lançamento do livro-caixa. Append-only: o model recusa update e delete, e o usuário de
 * banco da aplicação em produção não tem esses privilégios (database/sql/permissoes.sql).
 *
 * @property int $id
 * @property int $conta_id
 * @property Carbon $data
 * @property string $tipo
 * @property int $valor_centavos
 * @property CategoriaLancamento $categoria
 * @property string $descricao
 * @property string|null $comprovante_s3
 * @property string|null $comprovante_sha256
 * @property string $origem_tipo
 * @property int|null $origem_id
 * @property int|null $estorno_de_id
 * @property string|null $motivo
 * @property string|null $grupo
 * @property int $autor_id
 * @property Carbon $created_at
 * @property-read ContaCaixa $conta
 * @property-read LancamentoCaixa|null $estornadoPor
 * @property-read LancamentoCaixa|null $estornoDe
 * @property-read User $autor
 */
#[Fillable([
    'conta_id', 'data', 'tipo', 'valor_centavos', 'categoria', 'descricao', 'comprovante_s3',
    'comprovante_sha256', 'origem_tipo', 'origem_id', 'estorno_de_id', 'motivo', 'grupo', 'autor_id',
])]
class LancamentoCaixa extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'lancamentos_caixa';

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor_centavos' => 'integer',
            'categoria' => CategoriaLancamento::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RegistroImutavel('Lançamento do caixa não pode ser alterado; use estorno.'));
        static::deleting(fn () => throw new RegistroImutavel('Lançamento do caixa não pode ser apagado; use estorno.'));
    }

    /**
     * @return BelongsTo<ContaCaixa, $this>
     */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaCaixa::class, 'conta_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    /**
     * O estorno que anulou este lançamento (o original nunca é alterado; "estornado" é derivado).
     *
     * @return HasOne<LancamentoCaixa, $this>
     */
    public function estornadoPor(): HasOne
    {
        return $this->hasOne(self::class, 'estorno_de_id');
    }

    /**
     * @return BelongsTo<LancamentoCaixa, $this>
     */
    public function estornoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorno_de_id');
    }

    /**
     * Valor com sinal: entrada positiva, saída negativa.
     */
    public function valorComSinal(): int
    {
        return $this->tipo === 'entrada' ? $this->valor_centavos : -$this->valor_centavos;
    }
}
