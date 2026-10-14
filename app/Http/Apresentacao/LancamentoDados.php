<?php

namespace App\Http\Apresentacao;

use App\Models\LancamentoCaixa;

final class LancamentoDados
{
    /**
     * @return array<string, mixed>
     */
    public static function linha(LancamentoCaixa $lancamento): array
    {
        return [
            'id' => $lancamento->id,
            'data' => $lancamento->data->toDateString(),
            'conta' => $lancamento->conta->slug,
            'conta_nome' => $lancamento->conta->nome,
            'tipo' => $lancamento->tipo,
            'valor' => $lancamento->valor_centavos,
            'categoria' => $lancamento->categoria->value,
            'categoria_rotulo' => $lancamento->categoria->rotulo(),
            'descricao' => $lancamento->descricao,
            'origem' => $lancamento->origem_tipo,
            'tem_comprovante' => $lancamento->comprovante_s3 !== null,
            'estorno_de_id' => $lancamento->estorno_de_id,
            'estornado' => $lancamento->relationLoaded('estornadoPor') ? $lancamento->estornadoPor !== null : null,
            'motivo' => $lancamento->motivo,
            'autor' => $lancamento->relationLoaded('autor') ? $lancamento->autor->nome : null,
            'criado_em' => $lancamento->created_at->toIso8601String(),
        ];
    }
}
