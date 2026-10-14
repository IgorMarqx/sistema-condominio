<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $unidade_id
 * @property string|null $nome_informado
 * @property string $metodo
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $criado_em
 * @property-read User $user
 */
#[Fillable(['user_id', 'unidade_id', 'nome_informado', 'metodo', 'ip', 'user_agent', 'criado_em'])]
class SessaoLog extends Model
{
    public $timestamps = false;

    protected $table = 'sessoes_log';

    protected function casts(): array
    {
        return ['criado_em' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
