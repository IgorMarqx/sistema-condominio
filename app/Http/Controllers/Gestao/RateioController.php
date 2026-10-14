<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AjusteRateio;
use App\Models\Fatura;
use App\Models\Rateio;
use App\Models\Unidade;
use App\Services\Rateio\RateioService;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RateioController extends Controller
{
    public function show(Request $request, RateioService $rateios): Response
    {
        $disponiveis = $rateios->faturasDisponiveis();
        $mesSugerido = $disponiveis->map(fn (Fatura $f) => $f->competencia->mes_ref)->max() ?? today()->startOfMonth();
        $mes = $request->filled('mes') ? Mes::ref((string) $request->input('mes')) : Mes::ref($mesSugerido);

        $rateio = Rateio::query()->where('mes_ref', $mes->toDateString())->with(['faturas.medidor', 'faturas.competencia', 'confirmadoPor'])->first();

        $selecionadas = $request->has('faturas')
            ? $disponiveis->whereIn('id', array_map('intval', (array) $request->input('faturas')))
            : ($rateio && ! $rateio->isConfirmado() ? $rateio->faturas : $disponiveis->filter(fn (Fatura $f) => $f->competencia->mes_ref->lte($mes)));

        $vencimento = $request->filled('vencimento') ? Carbon::parse((string) $request->input('vencimento')) : null;

        return Inertia::render('gestao/rateio', [
            'mes' => $mes->format('Y-m'),
            'mes_rotulo' => Mes::rotulo($mes),
            'disponiveis' => $disponiveis->map(fn (Fatura $f) => [
                'id' => $f->id,
                'servico' => $f->medidor->concessionaria->servico(),
                'concessionaria' => $f->medidor->concessionaria->rotulo(),
                'mes_rotulo' => Mes::rotulo($f->competencia->mes_ref),
                'valor' => $f->valor_centavos,
                'vencimento' => $f->vencimento?->toDateString(),
            ])->values(),
            'rateio' => $rateio ? [
                'id' => $rateio->id,
                'status' => $rateio->status,
                'vencimento' => $rateio->vencimento->toDateString(),
                'confirmado_em' => $rateio->confirmado_em?->toIso8601String(),
                'confirmado_por' => $rateio->confirmadoPor?->nome,
                'detalhe' => $rateio->detalhe_json,
                'fatura_ids' => $rateio->faturas->pluck('id'),
            ] : null,
            'previa' => ($rateio?->isConfirmado() || $selecionadas->isEmpty()) ? null : $rateios->calcular($selecionadas->values(), $vencimento),
            'selecionadas' => $selecionadas->pluck('id')->values(),
            'ajustes_pendentes' => AjusteRateio::query()->whereNull('cobranca_item_id')->with('unidade')->latest('id')->get()->map(fn (AjusteRateio $a) => [
                'id' => $a->id,
                'unidade' => $a->unidade->numero,
                'descricao' => $a->descricao,
                'valor' => $a->valor_centavos,
                'motivo' => $a->motivo,
            ]),
            'unidades' => Unidade::query()->where('ativa', true)->ordemFixa()->pluck('numero'),
        ]);
    }

    public function store(Request $request, RateioService $rateios): RedirectResponse
    {
        $dados = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
            'faturas' => ['required', 'array', 'min:1'],
            'faturas.*' => ['integer'],
            'vencimento' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $rateios->gerarRascunho(
            Mes::ref($dados['mes']),
            array_values(array_map('intval', $dados['faturas'])),
            $request->user(),
            filled($dados['vencimento'] ?? null) ? Carbon::parse($dados['vencimento']) : null,
        );

        return redirect()->route('gestao.rateio', ['mes' => $dados['mes']])->with('sucesso', 'Rascunho do rateio gerado. Confira e confirme.');
    }

    public function confirmar(Request $request, Rateio $rateio, RateioService $rateios): RedirectResponse
    {
        $rateios->confirmar($rateio, $request->user());

        return redirect()->route('gestao.cobrancas', ['mes' => $rateio->mes_ref->format('Y-m')])->with('sucesso', 'Rateio confirmado e cobranças enviadas às unidades.');
    }

    public function destroy(Rateio $rateio, RateioService $rateios): RedirectResponse
    {
        $mes = $rateio->mes_ref->format('Y-m');
        $rateios->descartar($rateio);

        return redirect()->route('gestao.rateio', ['mes' => $mes])->with('sucesso', 'Rascunho descartado.');
    }

    public function ajuste(Request $request, RateioService $rateios): RedirectResponse
    {
        $dados = $request->validate([
            'unidade' => ['required', 'string', 'exists:unidades,numero'],
            'tipo' => ['required', 'in:credito,debito'],
            'valor' => ['required', 'string'],
            'descricao' => ['required', 'string', 'max:120'],
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        $valor = abs(Dinheiro::paraCentavos($dados['valor']));
        $unidade = Unidade::query()->where('numero', $dados['unidade'])->firstOrFail();

        $rateios->criarAjuste(
            $unidade,
            $dados['tipo'] === 'credito' ? -$valor : $valor,
            $dados['descricao'],
            $dados['motivo'],
            $request->user(),
            Rateio::query()->where('status', 'confirmado')->orderByDesc('mes_ref')->first(),
        );

        return back()->with('sucesso', 'Ajuste registrado: entra no próximo rateio do apto '.$unidade->numero.'.');
    }
}
