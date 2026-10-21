<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Apresentacao\CobrancaDados;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\Pagamento;
use App\Models\Rateio;
use App\Services\Cobrancas\PagamentoService;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cobranças do mês e confirmação dos comprovantes (conferidos no extrato do banco).
 */
class CobrancasController extends Controller
{
    public function index(Request $request, PagamentoService $pagamentos): Response
    {
        // Um rateio por conta: o mês aparece uma vez só no seletor.
        $meses = Rateio::query()->where('status', 'confirmado')->orderByDesc('mes_ref')->pluck('mes_ref')
            ->unique(fn ($m) => Mes::ref($m)->format('Y-m'))->values();
        $mes = $request->filled('mes') ? Mes::ref((string) $request->input('mes')) : ($meses->first() ? Mes::ref($meses->first()) : null);

        $cobrancas = $mes
            ? Cobranca::query()->where('mes_ref', $mes->toDateString())->with(['unidade', 'itens', 'pagamentos'])->get()
                ->sortBy(fn (Cobranca $c) => [$c->unidade->andar, $c->unidade->numero])->values()
            : collect();

        return Inertia::render('gestao/cobrancas', [
            'mes' => $mes?->format('Y-m'),
            'mes_rotulo' => $mes ? Mes::rotulo($mes) : null,
            'meses' => $meses->map(fn ($m) => ['valor' => Mes::ref($m)->format('Y-m'), 'rotulo' => Mes::rotulo(Mes::ref($m))])->values(),
            'cobrancas' => $cobrancas->map(fn (Cobranca $c) => CobrancaDados::detalhe($c, null) + [
                'em_aberto' => $pagamentos->emAberto($c),
            ])->values(),
            'totais' => [
                'cobrado' => (int) $cobrancas->sum('valor_centavos'),
                'recebido' => (int) $cobrancas->sum(fn (Cobranca $c) => $c->pagamentos->where('status', 'confirmado')->sum('valor_centavos')),
                'aguardando' => $cobrancas->filter(fn (Cobranca $c) => $c->pagamentos->contains('status', 'aguardando'))->count(),
            ],
            // Inadimplência de todos os meses (não só o escolhido).
            'inadimplencia' => Cobranca::query()->where('status', 'aberta')->whereDate('vencimento', '<', today())->with('unidade')->get()
                ->groupBy(fn (Cobranca $c) => $c->unidade->numero)
                ->map(fn ($lista, $numero) => ['unidade' => $numero, 'meses' => $lista->count(), 'valor' => (int) $lista->sum('valor_centavos')])
                ->sortKeys()->values(),
        ]);
    }

    public function confirmar(Request $request, Pagamento $pagamento, PagamentoService $pagamentos): RedirectResponse
    {
        $pagamentos->confirmar($pagamento, $request->user());

        return back()->with('sucesso', 'Pagamento do apto '.$pagamento->cobranca->unidade->numero.' confirmado e lançado no caixa.');
    }

    public function recusar(Request $request, Pagamento $pagamento, PagamentoService $pagamentos): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $pagamentos->recusar($pagamento, $dados['motivo'], $request->user());

        return back()->with('sucesso', 'Comprovante recusado. A unidade foi avisada.');
    }

    public function paga(Request $request, Cobranca $cobranca, PagamentoService $pagamentos): RedirectResponse
    {
        $pagamentos->marcarPaga($cobranca, $request->user());

        return back()->with('sucesso', 'Apto '.$cobranca->unidade->numero.' marcado como pago. O valor entrou no caixa.');
    }

    public function naoPaga(Request $request, Cobranca $cobranca, PagamentoService $pagamentos): RedirectResponse
    {
        $pagamentos->marcarNaoPaga($cobranca, $request->user());

        return back()->with('sucesso', 'Apto '.$cobranca->unidade->numero.' marcado como não pago.');
    }

    public function recebimento(Request $request, Cobranca $cobranca, PagamentoService $pagamentos): RedirectResponse
    {
        $dados = $request->validate([
            'comprovante' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
            'pago_em' => ['required', 'date', 'before_or_equal:today'],
            'valor' => ['required', 'string'],
        ]);

        $pagamentos->registrarRecebimento($cobranca, $request->file('comprovante'), Carbon::parse($dados['pago_em']), Dinheiro::paraCentavos($dados['valor']), $request->user());

        return back()->with('sucesso', 'Recebimento registrado.');
    }
}
