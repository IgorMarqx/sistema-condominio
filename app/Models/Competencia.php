<?php

namespace App\Models;

use App\Enums\StatusCompetencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $medidor_id
 * @property Carbon $mes_ref
 * @property Carbon|null $leitura_prevista
 * @property string|null $leitura_prevista_origem
 * @property Carbon|null $leitura_realizada
 * @property string|null $leitura_foto_s3
 * @property StatusCompetencia $status
 * @property-read Medidor $medidor
 * @property-read Fatura|null $faturaAtual
 */
#[Fillable(['medidor_id', 'mes_ref', 'leitura_prevista', 'leitura_prevista_origem', 'leitura_realizada', 'leitura_foto_s3', 'status'])]
class Competencia extends Model
{
    protected $table = 'competencias';

    protected function casts(): array
    {
        return [
            'mes_ref' => 'date',
            'leitura_prevista' => 'date',
            'leitura_realizada' => 'date',
            'status' => StatusCompetencia::class,
        ];
    }

    /**
     * @return BelongsTo<Medidor, $this>
     */
    public function medidor(): BelongsTo
    {
        return $this->belongsTo(Medidor::class);
    }

    /**
     * @return HasMany<Fatura, $this>
     */
    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    /**
     * A fatura vigente da competência (a rejeitada não conta).
     *
     * @return HasOne<Fatura, $this>
     */
    public function faturaAtual(): HasOne
    {
        return $this->hasOne(Fatura::class)->where('status', '!=', 'rejeitada')->latestOfMany();
    }

    /**
     * Avança o status sem nunca voltar (ex.: fatura que chega depois de confirmada não rebaixa).
     */
    public function avancarPara(StatusCompetencia $status): void
    {
        if ($status->ordem() > $this->status->ordem()) {
            $this->status = $status;
            $this->save();
        }
    }
}
