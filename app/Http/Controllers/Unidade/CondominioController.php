<?php

namespace App\Http\Controllers\Unidade;

use App\Enums\Concessionaria;
use App\Http\Apresentacao\FaturaDados;
use App\Http\Controllers\Controller;
use App\Models\Fatura;
use App\Models\Medidor;
use App\Services\Consultas;
use App\Services\Faturas\ValidadorFatura;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contas do condomínio: visíveis para todas as unidades, com o PDF original.
 */
class CondominioController extends Controller
{
    public function __invoke(Consultas $consultas, ValidadorFatura $validador): Response
    {
        $paineis = [];

        foreach (Concessionaria::cases() as $concessionaria) {
            $medidor = Medidor::query()->where('dono_tipo', 'condominio')->where('concessionaria', $concessionaria)->where('ativo', true)->first();

            if (! $medidor) {
                $paineis[$concessionaria->value] = null;

                continue;
            }

            $faturas = Fatura::query()
                ->where('medidor_id', $medidor->id)
                ->whereIn('status', ['confirmada', 'pendente_confirmacao', 'pendente_revisao'])
                ->with(['medidor', 'competencia', 'rateios'])
                ->get()
                ->sortByDesc(fn (Fatura $f) => $f->competencia->mes_ref)
                ->values();

            $ultima = $faturas->first();
            $variacao = $ultima ? $validador->variacaoConsumo($ultima) : null;

            $paineis[$concessionaria->value] = [
                'medidor' => ['identificador' => $medidor->identificador, 'descricao' => $medidor->descricao],
                'unidade_medida' => $concessionaria->unidadeMedida(),
                'ultima' => $ultima ? FaturaDados::resumo($ultima) : null,
                'variacao' => $variacao,
                'historico' => $consultas->historicoConsumo($medidor),
                'faturas' => $faturas->take(24)->map(fn (Fatura $f) => FaturaDados::resumo($f))->values(),
            ];
        }

        return Inertia::render('unidade/condominio', ['paineis' => $paineis]);
    }
}
