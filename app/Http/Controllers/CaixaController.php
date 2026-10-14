<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaLancamento;
use App\Http\Apresentacao\LancamentoDados;
use App\Models\ContaCaixa;
use App\Models\Fechamento;
use App\Models\LancamentoCaixa;
use App\Services\Caixa\CaixaService;
use App\Services\Configuracoes;
use App\Services\Consultas;
use App\Support\Dinheiro;
use App\Support\Mes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Caixa visível para todas as unidades: saldos, extrato por mês, prestações de contas.
 * O síndico usa a mesma leitura na gestão, com os botões de lançamento.
 */
class CaixaController extends Controller
{
    public function index(Request $request, CaixaService $caixa): Response
    {
        $saldos = $caixa->saldosPorConta();
        $ultimoFechamento = Fechamento::query()->orderByDesc('mes_ref')->first();

        return Inertia::render($request->user()->vePainelGestao() ? 'gestao/caixa' : 'unidade/caixa', [
            'saldo' => array_sum($saldos),
            'contas' => ContaCaixa::query()->orderBy('id')->get()->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'nome' => $c->nome,
                'saldo' => $saldos[$c->slug] ?? 0,
            ]),
            'ultimo_fechamento' => $ultimoFechamento ? [
                'mes' => $ultimoFechamento->mes_ref->format('Y-m'),
                'mes_nome' => Mes::nome($ultimoFechamento->mes_ref),
                'diferenca' => $ultimoFechamento->diferenca_centavos,
            ] : null,
            'meses' => $this->meses($request, $caixa),
            'filtro' => $request->only(['conta', 'categoria', 'busca']),
            'evolucao' => fn () => $caixa->evolucao(12),
            'previsao' => fn () => app(Consultas::class)->previsaoSaldo(),
            'categorias' => array_map(fn (CategoriaLancamento $c) => ['valor' => $c->value, 'rotulo' => $c->rotulo()], CategoriaLancamento::manuais()),
            'prestacoes' => Fechamento::query()->orderByDesc('mes_ref')->limit(24)->get()->map(fn (Fechamento $f) => [
                'id' => $f->id,
                'mes' => $f->mes_ref->format('Y-m'),
                'mes_rotulo' => Mes::rotulo($f->mes_ref),
                'diferenca' => $f->diferenca_centavos,
                'tem_pdf' => $f->pdf_s3 !== null,
            ]),
        ]);
    }

    /**
     * Extrato agrupado por mês, do mais recente; "ate" pagina para trás.
     *
     * @return list<array<string, mixed>>
     */
    private function meses(Request $request, CaixaService $caixa): array
    {
        $ate = $request->filled('ate') ? Mes::ref((string) $request->input('ate')) : today()->startOfMonth();
        $quantidade = 3;
        $resultado = [];

        $primeiro = LancamentoCaixa::query()->min('data');

        for ($i = 0; $i < $quantidade; $i++) {
            $mes = $ate->copy()->subMonthsNoOverflow($i);

            if ($primeiro && $mes->copy()->endOfMonth()->lt(Carbon::parse($primeiro)) && $i > 0) {
                break;
            }

            $lancamentos = $this->consulta($request)
                ->whereBetween('data', [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
                ->orderByDesc('data')->orderByDesc('id')
                ->get();

            $movimento = $caixa->movimentoDoMes($mes);
            $resultado[] = [
                'mes' => $mes->format('Y-m'),
                'mes_rotulo' => ucfirst(Mes::nome($mes)),
                'ano' => $mes->year,
                'entradas' => $movimento['entradas'],
                'saidas' => $movimento['saidas'],
                'fechado' => Fechamento::query()->where('mes_ref', $mes->toDateString())->exists(),
                'lancamentos' => $lancamentos->map(fn (LancamentoCaixa $l) => LancamentoDados::linha($l) + [
                    'pode_ver_comprovante' => $l->comprovante_s3 !== null && ArquivosController::podeVerLancamento($request->user(), $l),
                ])->values(),
            ];
        }

        return $resultado;
    }

    /**
     * @return Builder<LancamentoCaixa>
     */
    private function consulta(Request $request)
    {
        return LancamentoCaixa::query()
            ->with(['conta', 'estornadoPor', 'estornoDe', 'autor'])
            ->when($request->filled('conta'), fn ($q) => $q->whereHas('conta', fn ($c) => $c->where('slug', $request->input('conta'))))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->input('categoria')))
            ->when($request->filled('busca'), fn ($q) => $q->where('descricao', 'like', '%'.$request->input('busca').'%'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $lancamentos = $this->consulta($request)->orderBy('data')->orderBy('id')->get();

        return response()->streamDownload(function () use ($lancamentos) {
            $saida = fopen('php://output', 'w');

            if ($saida === false) {
                return;
            }

            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['id', 'data', 'conta', 'tipo', 'valor', 'categoria', 'descricao', 'estorno_de', 'motivo', 'sha256_comprovante'], ';');

            foreach ($lancamentos as $l) {
                fputcsv($saida, [
                    $l->id,
                    $l->data->format('d/m/Y'),
                    $l->conta->nome,
                    $l->tipo,
                    number_format($l->valorComSinal() / 100, 2, ',', ''),
                    $l->categoria->rotulo(),
                    $l->descricao,
                    $l->estorno_de_id,
                    $l->motivo,
                    $l->comprovante_sha256,
                ], ';');
            }

            fclose($saida);
        }, 'extrato-caixa-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request, CaixaService $caixa, Configuracoes $configuracoes): \Illuminate\Http\Response
    {
        $mes = $request->filled('mes') ? Mes::ref((string) $request->input('mes')) : today()->startOfMonth();
        $inicio = $mes->copy()->subMonthsNoOverflow(2);

        $lancamentos = $this->consulta($request)
            ->whereBetween('data', [$inicio->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
            ->orderBy('data')->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('pdf.extrato', [
            'edificio' => $configuracoes->get('nome_edificio'),
            'periodo' => Mes::rotulo($inicio).' a '.Mes::rotulo($mes),
            'saldoInicial' => $caixa->saldo(null, $inicio->copy()->subDay()),
            'saldoFinal' => $caixa->saldo(null, $mes->copy()->endOfMonth()),
            'saldos' => $caixa->saldosPorConta($mes->copy()->endOfMonth()),
            'contas' => ContaCaixa::query()->pluck('nome', 'slug'),
            'lancamentos' => $lancamentos,
            'formatar' => fn (int $c) => Dinheiro::formatar($c),
            'gerado' => now('America/Sao_Paulo')->format('d/m/Y H:i'),
        ])->setPaper('a4');

        return $pdf->download('extrato-caixa-'.$mes->format('Y-m').'.pdf');
    }
}
