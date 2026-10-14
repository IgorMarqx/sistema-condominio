<?php

namespace App\Http\Controllers\Unidade;

use App\Enums\StatusCobranca;
use App\Http\Apresentacao\CobrancaDados;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Services\Cobrancas\PagamentoService;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CobrancaController extends Controller
{
    public function show(Request $request, PagamentoService $pagamentos, ?Cobranca $cobranca = null): Response
    {
        $unidadeId = $request->user()->unidade_id;

        if ($cobranca?->exists) {
            abort_unless($cobranca->unidade_id === $unidadeId, 404);
        } else {
            $cobranca = Cobranca::query()
                ->where('unidade_id', $unidadeId)
                ->where('status', '!=', StatusCobranca::Cancelada)
                ->orderByRaw("FIELD(status, 'aberta', 'aguardando_confirmacao', 'paga')")
                ->orderByDesc('mes_ref')
                ->first();
        }

        $cobranca?->load(['unidade', 'itens.fatura.medidor', 'pagamentos', 'rateio.faturas.medidor', 'rateio.faturas.competencia']);

        return Inertia::render('unidade/cobranca', [
            'cobranca' => $cobranca ? CobrancaDados::detalhe($cobranca, $cobranca->status === StatusCobranca::Paga ? null : $pagamentos->pixCopiaCola($cobranca)) + [
                'em_aberto' => $pagamentos->emAberto($cobranca),
                'faturas' => $cobranca->rateio->faturas->map(fn ($f) => [
                    'id' => $f->id,
                    'rotulo' => $f->medidor->concessionaria->rotulo().' · '.$f->competencia->mes_ref->format('m/Y'),
                    'valor' => $f->valor_centavos,
                ])->values(),
            ] : null,
            'historico' => Cobranca::query()->where('unidade_id', $unidadeId)->with('unidade')->orderByDesc('mes_ref')->limit(12)->get()
                ->map(fn (Cobranca $c) => CobrancaDados::resumo($c)),
        ]);
    }

    public function comprovante(Request $request, Cobranca $cobranca, PagamentoService $pagamentos): RedirectResponse
    {
        abort_unless($cobranca->unidade_id === $request->user()->unidade_id, 404);

        $dados = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,heic,webp'],
            'pago_em' => ['required', 'date', 'before_or_equal:today'],
            'valor' => ['nullable', 'string'],
        ], [], ['arquivo' => 'comprovante', 'pago_em' => 'data do pagamento']);

        $valor = filled($dados['valor'] ?? null) ? Dinheiro::paraCentavos($dados['valor']) : null;

        $pagamentos->enviarComprovante(
            $cobranca,
            $request->file('arquivo'),
            Carbon::parse($dados['pago_em']),
            $request->user(),
            $request->session()->get('nome_informado'),
            $valor,
        );

        return back()->with('sucesso', 'Comprovante enviado. O síndico confirma ao conferir o extrato.');
    }
}
