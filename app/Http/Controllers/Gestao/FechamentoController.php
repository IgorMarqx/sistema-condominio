<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Fechamento;
use App\Services\Caixa\FechamentoService;
use App\Support\Dinheiro;
use App\Support\Mes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FechamentoController extends Controller
{
    public function show(FechamentoService $fechamentos): Response
    {
        $proximo = $fechamentos->proximoMes();

        return Inertia::render('gestao/fechamento', [
            'previa' => $proximo ? $fechamentos->previa($proximo) + ['mes_rotulo' => Mes::rotulo($proximo)] : null,
            'fechamentos' => Fechamento::query()->with('fechadoPor')->orderByDesc('mes_ref')->get()->map(fn (Fechamento $f) => [
                'id' => $f->id,
                'mes' => $f->mes_ref->format('Y-m'),
                'mes_rotulo' => Mes::rotulo($f->mes_ref),
                'saldo_calculado' => $f->saldo_calculado_centavos,
                'saldo_banco' => $f->saldo_banco_centavos,
                'diferenca' => $f->diferenca_centavos,
                'observacao' => $f->observacao,
                'hash_auditoria' => $f->hash_auditoria,
                'fechado_em' => $f->fechado_em->toIso8601String(),
                'fechado_por' => $f->fechadoPor?->nome,
                'tem_pdf' => $f->pdf_s3 !== null,
            ]),
        ]);
    }

    public function store(Request $request, FechamentoService $fechamentos): RedirectResponse
    {
        $dados = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
            'saldos' => ['required', 'array'],
            'saldos.*' => ['required', 'string'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ], [], ['saldos.*' => 'saldo do extrato']);

        $fechamento = $fechamentos->fechar(
            Mes::ref($dados['mes']),
            array_map(fn ($v) => Dinheiro::paraCentavos((string) $v), $dados['saldos']),
            $dados['observacao'] ?? null,
            $request->user(),
        );

        return back()->with('sucesso', Mes::rotulo($fechamento->mes_ref).' fechado. A prestação de contas foi gerada.');
    }
}
