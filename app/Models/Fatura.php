<?php

namespace App\Models;

use App\Enums\StatusFatura;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $competencia_id
 * @property int $medidor_id
 * @property int|null $anexo_id
 * @property int|null $email_id
 * @property string $origem
 * @property int|null $valor_centavos
 * @property Carbon|null $vencimento
 * @property Carbon|null $emissao
 * @property string|null $consumo
 * @property string|null $unidade_medida
 * @property Carbon|null $leitura_anterior
 * @property Carbon|null $leitura_atual
 * @property Carbon|null $proxima_leitura
 * @property string|null $chave_nf3e
 * @property string|null $linha_digitavel
 * @property string|null $pix_copia_cola
 * @property list<array{descricao: string, valor_centavos: int}>|null $itens_json
 * @property string|null $texto_extraido
 * @property string|null $extrator
 * @property string|null $extrator_versao
 * @property string|null $confianca
 * @property list<array{regra: string, ok: bool, mensagem: string}>|null $validacoes_json
 * @property StatusFatura $status
 * @property string|null $motivo_revisao
 * @property int|null $confirmada_por
 * @property Carbon|null $confirmada_em
 * @property Carbon|null $paga_em
 * @property Carbon $created_at
 * @property-read Competencia $competencia
 * @property-read Medidor $medidor
 * @property-read Anexo|null $anexo
 * @property-read EmailRecebido|null $email
 */
#[Fillable([
    'competencia_id', 'medidor_id', 'anexo_id', 'email_id', 'origem', 'valor_centavos', 'vencimento',
    'emissao', 'consumo', 'unidade_medida', 'leitura_anterior', 'leitura_atual', 'proxima_leitura',
    'chave_nf3e', 'linha_digitavel', 'pix_copia_cola', 'itens_json', 'texto_extraido', 'extrator',
    'extrator_versao', 'confianca', 'validacoes_json', 'status', 'motivo_revisao', 'confirmada_por',
    'confirmada_em', 'paga_em',
])]
#[Hidden(['texto_extraido'])]
class Fatura extends Model
{
    protected $table = 'faturas';

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'vencimento' => 'date',
            'emissao' => 'date',
            'consumo' => 'decimal:3',
            'leitura_anterior' => 'date',
            'leitura_atual' => 'date',
            'proxima_leitura' => 'date',
            'itens_json' => 'array',
            'validacoes_json' => 'array',
            'confianca' => 'decimal:3',
            'status' => StatusFatura::class,
            'confirmada_em' => 'datetime',
            'paga_em' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Competencia, $this>
     */
    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    /**
     * @return BelongsTo<Medidor, $this>
     */
    public function medidor(): BelongsTo
    {
        return $this->belongsTo(Medidor::class);
    }

    /**
     * @return BelongsTo<Anexo, $this>
     */
    public function anexo(): BelongsTo
    {
        return $this->belongsTo(Anexo::class);
    }

    /**
     * @return BelongsTo<EmailRecebido, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(EmailRecebido::class, 'email_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmada_por');
    }

    /**
     * @return BelongsToMany<Rateio, $this>
     */
    public function rateios(): BelongsToMany
    {
        return $this->belongsToMany(Rateio::class, 'rateio_faturas');
    }

    public function isCondominio(): bool
    {
        return $this->medidor->isCondominio();
    }

    /**
     * @param  Builder<Fatura>  $query
     */
    public function scopeDoCondominio($query): void
    {
        $query->whereHas('medidor', fn ($m) => $m->where('dono_tipo', 'condominio'));
    }

    /**
     * @param  Builder<Fatura>  $query
     */
    public function scopeDaUnidade($query, int $unidadeId): void
    {
        $query->whereHas('medidor', fn ($m) => $m->where('dono_tipo', 'unidade')->where('unidade_id', $unidadeId));
    }
}
