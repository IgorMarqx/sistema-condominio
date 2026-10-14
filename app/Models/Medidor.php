<?php

namespace App\Models;

use App\Enums\Concessionaria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $dono_tipo
 * @property int|null $unidade_id
 * @property Concessionaria $concessionaria
 * @property string $identificador
 * @property string|null $descricao
 * @property string|null $senha_pdf
 * @property bool $ativo
 * @property-read Unidade|null $unidade
 */
#[Fillable(['dono_tipo', 'unidade_id', 'concessionaria', 'identificador', 'descricao', 'senha_pdf', 'ativo'])]
#[Hidden(['senha_pdf'])]
class Medidor extends Model
{
    protected $table = 'medidores';

    protected function casts(): array
    {
        return [
            'concessionaria' => Concessionaria::class,
            'senha_pdf' => 'encrypted',
            'ativo' => 'boolean',
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
     * @return HasMany<Competencia, $this>
     */
    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class);
    }

    /**
     * @return HasMany<Fatura, $this>
     */
    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    public function isCondominio(): bool
    {
        return $this->dono_tipo === 'condominio';
    }

    public function rotulo(): string
    {
        $dono = $this->isCondominio() ? 'condomínio' : 'apto '.$this->unidade?->numero;

        return $this->concessionaria->rotulo().' · '.$dono;
    }

    /**
     * Identificador só com dígitos: o PDF às vezes imprime "3/1234567-8", o cadastro "312345678".
     */
    public static function normalizarIdentificador(string $identificador): string
    {
        return ltrim(preg_replace('/\D/', '', $identificador) ?? '', '0');
    }
}
