<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $chave
 * @property mixed $valor
 */
#[Fillable(['chave', 'valor'])]
class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['valor' => 'json'];
    }
}
