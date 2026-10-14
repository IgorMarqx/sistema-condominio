<?php

namespace App\Models;

use App\Exceptions\RegistroImutavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ator_tipo
 * @property int|null $ator_id
 * @property string|null $ator_rotulo
 * @property string|null $nome_informado
 * @property string $acao
 * @property string|null $entidade
 * @property int|null $entidade_id
 * @property string|null $antes_json
 * @property string|null $depois_json
 * @property string|null $ip
 * @property string|null $hash_anterior
 * @property string $hash
 * @property string $registrado_em
 * @property Carbon $created_at
 */
#[Fillable([
    'ator_tipo', 'ator_id', 'ator_rotulo', 'nome_informado', 'acao', 'entidade', 'entidade_id',
    'antes_json', 'depois_json', 'ip', 'hash_anterior', 'hash', 'registrado_em',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    /**
     * Sem cast: o hash é calculado sobre o texto gravado, byte a byte.
     *
     * @return array<string, mixed>|null
     */
    public function antes(): ?array
    {
        return $this->antes_json === null ? null : json_decode($this->antes_json, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function depois(): ?array
    {
        return $this->depois_json === null ? null : json_decode($this->depois_json, true);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RegistroImutavel('O registro de auditoria não pode ser alterado.'));
        static::deleting(fn () => throw new RegistroImutavel('O registro de auditoria não pode ser apagado.'));
    }

    /**
     * Quem fez, do jeito que a tela mostra: "302 — Maria", "Igor Marques (síndico)", "Sistema".
     */
    public function quem(): string
    {
        $rotulo = $this->ator_rotulo ?? 'Sistema';

        return $this->nome_informado ? $rotulo.' — '.$this->nome_informado : $rotulo;
    }
}
