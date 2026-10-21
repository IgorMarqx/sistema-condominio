<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\Concessionaria;
use App\Http\Controllers\Controller;
use App\Services\Faturas\FaturaService;
use App\Services\Rateio\RateioService;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Conta do condomínio lançada no painel: valor e consumo do mês, já dividida entre as unidades.
 */
class ContasController extends Controller
{
    public function store(Request $request, FaturaService $faturas, RateioService $rateios): RedirectResponse
    {
        $dados = $request->validate([
            'concessionaria' => ['required', 'in:energisa,cagepa'],
            'valor' => ['required', 'string', 'regex:/^\s*(R\$)?\s*[\d.,]+\s*$/'],
            'consumo' => ['nullable', 'string', 'regex:/^\d+([.,]\d{1,3})?$/'],
        ], [], ['valor' => 'valor', 'consumo' => 'consumo']);

        $concessionaria = Concessionaria::from($dados['concessionaria']);
        $consumo = $request->filled('consumo') ? $request->string('consumo')->replace(',', '.')->toString() : null;
        $mes = Mes::ref(today());

        $rateio = DB::transaction(fn () => $rateios->ratear(
            $faturas->lancarManual($concessionaria, $mes, Dinheiro::paraCentavos($dados['valor']), $consumo, $request->user()),
            $request->user(),
        ));

        return back()->with('sucesso', 'Conta da '.$concessionaria->rotulo().' lançada: '.Dinheiro::formatar($rateio->total_centavos)
            .' dividido entre '.$rateio->cobrancas()->count().' apartamentos.');
    }
}
