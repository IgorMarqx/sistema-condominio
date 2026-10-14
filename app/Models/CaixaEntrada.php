<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $dono_tipo
 * @property int|null $unidade_id
 * @property string $endereco
 * @property bool $ativo
 * @property-read Unidade|null $unidade
 */
#[Fillable(['dono_tipo', 'unidade_id', 'endereco', 'ativo', 'desativado_em'])]
class CaixaEntrada extends Model
{
    protected $table = 'caixas_entrada';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'desativado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unidade, $this>
     */
    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    /**
     * @return HasMany<EmailRecebido, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailRecebido::class);
    }

    public function isCondominio(): bool
    {
        return $this->dono_tipo === 'condominio';
    }
}
