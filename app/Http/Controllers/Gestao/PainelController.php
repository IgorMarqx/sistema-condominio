<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Http\Apresentacao\FaturaDados;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\EmailRecebido;
use App\Models\Fatura;
use App\Models\Pagamento;
use App\Models\Rateio;
use App\Models\Unidade;
use App\Services\Caixa\CaixaService;
use App\Services\Caixa\FechamentoService;
use App\Services\Rateio\RateioService;
use App\Support\Mes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request, CaixaService $caixa, RateioService $rateios, FechamentoService $fechamentos): Response
    {
        $rateio = Rateio::query()->where('status', 'confirmado')->orderByDesc('mes_ref')->first();
        $cobrancas = $rateio
            ? Cobranca::query()->where('rateio_id', $rateio->id)->with(['unidade', 'pagamentos'])->get()->keyBy('unidade_id')
            : collect();

        $emAberto = $cobrancas
            // Comprovante enviado conta como "conferir", não como aberto (igual ao mockup).
            ->filter(fn (Cobranca $c) => $c->status === StatusCobranca::Aberta)
            ->sum(fn (Cobranca $c) => $c->valor_centavos - (int) $c->pagamentos->where('status', 'confirmado')->sum('valor_centavos'));

        $faturasPendentes = Fatura::query()->doCondominio()
            ->whereIn('status', [StatusFatura::PendenteConfirmacao, StatusFatura::PendenteRevisao])
            ->with(['medidor', 'competencia'])
            ->latest('id')
            ->get();

        $comprovantes = Pagamento::query()->where('status', 'aguardando')->with('cobranca.unidade')->get();
        $emailsRevisao = EmailRecebido::query()->where('status', 'revisao')
            ->whereHas('caixaEntrada', fn ($q) => $q->where('dono_tipo', 'condominio'))->count();

        $proximoFechamento = $fechamentos->proximoMes();
        $podeFechar = $proximoFechamento && $fechamentos->previa($proximoFechamento)['pode_fechar'];

        return Inertia::render('gestao/painel', [
            'resumo' => [
                'recebidas' => $cobrancas->where('status', StatusCobranca::Paga)->count(),
                'total' => $cobrancas->count(),
                'em_aberto' => $emAberto,
                'caixa' => $caixa->saldo(),
            ],
            'pendencias' => [
                'faturas' => $faturasPendentes->map(fn (Fatura $f) => FaturaDados::resumo($f))->values(),
                'comprovantes' => [
                    'quantidade' => $comprovantes->count(),
                    'aptos' => $comprovantes->map(fn (Pagamento $p) => $p->cobranca->unidade->numero)->unique()->sort()->values(),
                    'mes' => $comprovantes->first()?->cobranca->mes_ref->format('Y-m'),
                ],
                'emails_revisao' => $emailsRevisao,
                'faturas_para_ratear' => $rateios->faturasDisponiveis()->count(),
                'fechamento' => $podeFechar ? ['mes' => $proximoFechamento->format('Y-m'), 'mes_rotulo' => Mes::rotulo($proximoFechamento)] : null,
            ],
            'cobrancas' => $rateio ? [
                'mes' => $rateio->mes_ref->format('Y-m'),
                'mes_nome' => Mes::nome($rateio->mes_ref),
                'vencimento' => $rateio->vencimento->toDateString(),
                'unidades' => Unidade::query()->where('ativa', true)->orderByDesc('andar')->orderBy('numero')->get()
                    ->map(function (Unidade $u) use ($cobrancas) {
                        $c = $cobrancas->get($u->id);

                        return [
                            'numero' => $u->numero,
                            'andar' => $u->andar,
                            'status' => $c?->status->value,
                            'vencida' => $c?->vencida() ?? false,
                            'valor' => $c?->valor_centavos,
                        ];
                    })->values(),
            ] : null,
        ]);
    }
}
