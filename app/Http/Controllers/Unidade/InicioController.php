<?php

namespace App\Http\Controllers\Unidade;

use App\Enums\StatusCobranca;
use App\Http\Apresentacao\CobrancaDados;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\Notificacao;
use App\Services\Caixa\CaixaService;
use App\Services\Consultas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InicioController extends Controller
{
    public function __invoke(Request $request, CaixaService $caixa, Consultas $consultas): Response
    {
        $user = $request->user();
        $movimento = $caixa->movimentoDoMes(today()->startOfMonth());

        $cobranca = Cobranca::query()
            ->where('unidade_id', $user->unidade_id)
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->with('unidade', 'itens')
            ->orderByRaw("FIELD(status, 'aberta', 'aguardando_confirmacao', 'paga')")
            ->orderByDesc('mes_ref')
            ->first();

        return Inertia::render('unidade/inicio', [
            'cobranca' => $cobranca ? CobrancaDados::resumo($cobranca) + [
                'composicao' => $cobranca->itens->pluck('tipo')->unique()->values(),
            ] : null,
            'caixa' => [
                'saldo' => $caixa->saldo(),
                'variacao_mes' => $movimento['entradas'] - $movimento['saidas'],
            ],
            'leituras' => $consultas->proximasLeiturasCondominio(),
            'avisos' => Notificacao::query()->doUsuario($user)->latest('id')->limit(4)->get()
                ->map(fn (Notificacao $n) => AvisosController::dados($n)),
        ]);
    }
}
