<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $email_id
 * @property string $nome
 * @property string $s3_key
 * @property string $sha256
 * @property int $tamanho
 * @property string $mime
 */
#[Fillable(['email_id', 'nome', 's3_key', 'sha256', 'tamanho', 'mime', 'enviado_por'])]
class Anexo extends Model
{
    protected $table = 'anexos';

    /**
     * @return BelongsTo<EmailRecebido, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(EmailRecebido::class, 'email_id');
    }
}
