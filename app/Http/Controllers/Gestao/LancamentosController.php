<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\CategoriaLancamento;
use App\Enums\StatusCompetencia;
use App\Http\Controllers\Controller;
use App\Models\ContaCaixa;
use App\Models\Fatura;
use App\Models\LancamentoCaixa;
use App\Services\Caixa\CaixaService;
use App\Services\Cobrancas\PagamentoService;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LancamentosController extends Controller
{
    public function store(Request $request, CaixaService $caixa): RedirectResponse
    {
        $dados = $request->validate([
            'conta' => ['required', 'exists:contas_caixa,slug'],
            'tipo' => ['required', 'in:entrada,saida'],
            'valor' => ['required', 'string'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'categoria' => ['required', Rule::in(array_map(fn ($c) => $c->value, CategoriaLancamento::manuais()))],
            'descricao' => ['required', 'string', 'max:255'],
            'comprovante' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ], [], ['comprovante' => 'comprovante', 'descricao' => 'descrição']);

        $caixa->lancar(
            ContaCaixa::query()->where('slug', $dados['conta'])->firstOrFail(),
            Carbon::parse($dados['data']),
            $dados['tipo'],
            Dinheiro::paraCentavos($dados['valor']),
            CategoriaLancamento::from($dados['categoria']),
            $dados['descricao'],
            $request->user(),
            $request->file('comprovante'),
        );

        return back()->with('sucesso', 'Lançamento registrado.');
    }

    public function estornar(Request $request, LancamentoCaixa $lancamento, CaixaService $caixa, PagamentoService $pagamentos): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($lancamento, $dados, $request, $caixa, $pagamentos) {
            $caixa->estornar($lancamento, $dados['motivo'], $request->user());

            // Efeitos na origem: cobrança volta a ficar aberta; fatura do condomínio volta a "a pagar".
            $pagamentos->aposEstorno($lancamento);

            if ($lancamento->origem_tipo === 'fatura' && ($fatura = Fatura::query()->find($lancamento->origem_id))) {
                $fatura->update(['paga_em' => null]);
                $fatura->competencia->update([
                    'status' => $fatura->rateios()->exists() ? StatusCompetencia::Rateada : StatusCompetencia::Confirmada,
                ]);
            }
        });

        return back()->with('sucesso', 'Lançamento nº '.$lancamento->id.' estornado.');
    }

    public function transferir(Request $request, CaixaService $caixa): RedirectResponse
    {
        $dados = $request->validate([
            'origem' => ['required', 'exists:contas_caixa,slug'],
            'destino' => ['required', 'exists:contas_caixa,slug', 'different:origem'],
            'valor' => ['required', 'string'],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'descricao' => ['required', 'string', 'max:200'],
            'comprovante' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ]);

        $caixa->transferir(
            ContaCaixa::query()->where('slug', $dados['origem'])->firstOrFail(),
            ContaCaixa::query()->where('slug', $dados['destino'])->firstOrFail(),
            Dinheiro::paraCentavos($dados['valor']),
            Carbon::parse($dados['data']),
            $dados['descricao'],
            $request->user(),
            $request->file('comprovante'),
        );

        return back()->with('sucesso', 'Transferência registrada.');
    }
}
