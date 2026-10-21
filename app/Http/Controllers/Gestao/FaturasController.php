<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\StatusFatura;
use App\Http\Apresentacao\FaturaDados;
use App\Http\Controllers\Controller;
use App\Http\Requests\CamposFaturaRequest;
use App\Models\Fatura;
use App\Models\LancamentoCaixa;
use App\Models\Medidor;
use App\Models\Unidade;
use App\Services\Faturas\FaturaService;
use App\Services\Ingestao\ProcessadorPdf;
use App\Services\Rateio\RateioService;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FaturasController extends Controller
{
    public function index(Request $request): Response
    {
        $condominio = Fatura::query()->doCondominio()
            ->with(['medidor', 'competencia', 'rateios'])
            ->latest('id')
            ->limit(60)
            ->get();

        // Contas individuais: o síndico vê se chegou e o status; valor só com autorização da unidade.
        $mes = $request->filled('mes') ? Mes::ref((string) $request->input('mes')) : today()->startOfMonth();
        $individuais = Fatura::query()->where('status', '!=', 'rejeitada')
            ->whereHas('medidor', fn ($q) => $q->where('dono_tipo', 'unidade'))
            ->whereHas('competencia', fn ($q) => $q->where('mes_ref', $mes->toDateString()))
            ->with(['medidor.unidade', 'competencia'])
            ->get();

        return Inertia::render('gestao/faturas', [
            'pendentes' => $condominio->filter(fn (Fatura $f) => in_array($f->status, [StatusFatura::PendenteConfirmacao, StatusFatura::PendenteRevisao], true))
                ->map(fn (Fatura $f) => FaturaDados::resumo($f))->values(),
            'condominio' => $condominio->map(fn (Fatura $f) => FaturaDados::resumo($f))->values(),
            'unidades' => [
                'mes' => $mes->format('Y-m'),
                'mes_rotulo' => Mes::rotulo($mes),
                'linhas' => Unidade::query()->where('ativa', true)->ordemFixa()->get()->map(function (Unidade $u) use ($individuais) {
                    $daUnidade = $individuais->filter(fn (Fatura $f) => $f->medidor->unidade_id === $u->id);

                    return [
                        'numero' => $u->numero,
                        'autoriza' => $u->autoriza_admin_ver_faturas,
                        'faturas' => $daUnidade->map(fn (Fatura $f) => FaturaDados::resumo($f, $u->autoriza_admin_ver_faturas))->values(),
                    ];
                })->values(),
            ],
            'medidores' => Medidor::query()->where('dono_tipo', 'condominio')->where('ativo', true)->get()
                ->map(fn (Medidor $m) => ['id' => $m->id, 'concessionaria' => $m->concessionaria->value, 'rotulo' => $m->concessionaria->rotulo().' · '.$m->identificador])->values(),
        ]);
    }

    public function show(Fatura $fatura): Response
    {
        abort_unless($fatura->medidor->isCondominio(), 404);
        $fatura->load(['medidor', 'competencia', 'anexo', 'email', 'confirmadaPor', 'rateios']);

        $pagamento = LancamentoCaixa::query()->where('origem_tipo', 'fatura')->where('origem_id', $fatura->id)
            ->whereDoesntHave('estornadoPor')->first();

        return Inertia::render('gestao/fatura', [
            'fatura' => FaturaDados::detalhe($fatura),
            'pagamento' => $pagamento ? ['id' => $pagamento->id, 'data' => $pagamento->data->toDateString(), 'valor' => $pagamento->valor_centavos] : null,
        ]);
    }

    public function confirmar(Request $request, Fatura $fatura, FaturaService $faturas, RateioService $rateios): RedirectResponse
    {
        $rateio = DB::transaction(fn () => $rateios->ratear($faturas->confirmar($fatura, $request->user()), $request->user()));

        return redirect()->route('gestao.painel')->with('sucesso', 'Fatura '.$fatura->medidor->concessionaria->rotulo().' confirmada e dividida entre '
            .$rateio->cobrancas()->count().' apartamentos.');
    }

    public function corrigir(CamposFaturaRequest $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo da correção']);
        $faturas->corrigir($fatura, $request->campos(), (string) $request->input('motivo'), $request->user());

        return back()->with('sucesso', 'Campo corrigido. A correção ficou na auditoria.');
    }

    public function rejeitar(Request $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $faturas->rejeitar($fatura, $dados['motivo'], $request->user());

        return redirect()->route('gestao.faturas')->with('sucesso', 'Fatura rejeitada.');
    }

    public function pagar(Request $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $dados = $request->validate([
            'data' => ['required', 'date', 'before_or_equal:today'],
            'comprovante' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ]);

        $faturas->pagarCondominio($fatura, Carbon::parse($dados['data']), $request->file('comprovante'), $request->user());

        return back()->with('sucesso', 'Pagamento lançado no caixa.');
    }

    public function anexar(Request $request, Fatura $fatura, FaturaService $faturas): RedirectResponse
    {
        $request->validate(['arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic', 'max:10240']], [], ['arquivo' => 'arquivo da fatura']);

        $faturas->anexarArquivo($fatura, $request->file('arquivo'), $request->user());

        return back()->with('sucesso', 'Arquivo anexado à fatura.');
    }

    public function upload(CamposFaturaRequest $request, ProcessadorPdf $processador): RedirectResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'medidor_id' => ['required', 'integer'],
        ], [], ['arquivo' => 'PDF da fatura', 'medidor_id' => 'medidor']);

        $medidor = Medidor::query()->where('dono_tipo', 'condominio')->findOrFail($request->integer('medidor_id'));

        $resultado = $processador->processar(
            (string) file_get_contents($request->file('arquivo')->getRealPath()),
            $request->file('arquivo')->getClientOriginalName(),
            null,
            null,
            $medidor,
            $request->campos(),
            $request->user()->id,
        );

        return match ($resultado->situacao) {
            'fatura' => redirect()->route('gestao.faturas.show', $resultado->fatura)->with('sucesso', 'Fatura registrada. Confira e confirme.'),
            'duplicado' => back()->with('erro', 'Este PDF já foi enviado antes.'),
            default => back()->with('erro', (string) $resultado->motivo),
        };
    }
}
