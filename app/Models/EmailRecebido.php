<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $caixa_entrada_id
 * @property string $destinatario
 * @property string|null $remetente
 * @property string|null $assunto
 * @property string|null $message_id
 * @property string $s3_eml
 * @property int $tamanho
 * @property string|null $spf
 * @property string|null $dkim
 * @property string|null $tipo
 * @property string $status
 * @property string|null $erro
 * @property string|null $verificacao_codigo
 * @property string|null $verificacao_link
 * @property list<string>|null $links
 * @property Carbon $recebido_em
 * @property Carbon|null $processado_em
 * @property-read CaixaEntrada|null $caixaEntrada
 */
#[Fillable([
    'caixa_entrada_id', 'destinatario', 'remetente', 'assunto', 'message_id', 's3_eml', 'tamanho',
    'spf', 'dkim', 'tipo', 'status', 'erro', 'verificacao_codigo', 'verificacao_link', 'links',
    'recebido_em', 'processado_em',
])]
class EmailRecebido extends Model
{
    protected $table = 'emails_recebidos';

    protected function casts(): array
    {
        return [
            'links' => 'array',
            'recebido_em' => 'datetime',
            'processado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CaixaEntrada, $this>
     */
    public function caixaEntrada(): BelongsTo
    {
        return $this->belongsTo(CaixaEntrada::class);
    }

    /**
     * @return HasMany<Anexo, $this>
     */
    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class, 'email_id');
    }

    public function remetenteVerificado(): bool
    {
        return $this->spf === 'pass' && $this->dkim === 'pass';
    }
}
