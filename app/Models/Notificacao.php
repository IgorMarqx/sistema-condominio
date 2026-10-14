<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $destino
 * @property int|null $unidade_id
 * @property string $tipo
 * @property string $titulo
 * @property string|null $mensagem
 * @property string|null $url
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $lida_em
 * @property Carbon $created_at
 */
#[Fillable(['destino', 'unidade_id', 'tipo', 'titulo', 'mensagem', 'url', 'payload', 'chave_unica', 'lida_em'])]
class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'lida_em' => 'datetime',
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
     * @param  Builder<Notificacao>  $query
     */
    public function scopeDoUsuario($query, User $user): void
    {
        if ($user->isUnidade()) {
            $query->where('destino', 'unidade')->where('unidade_id', $user->unidade_id);

            return;
        }

        $query->where('destino', 'admin');
    }
}
