<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Auditoria\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $registros = AuditLog::query()
            ->when($request->filled('acao'), fn ($q) => $q->where('acao', 'like', $request->input('acao').'%'))
            ->when($request->filled('busca'), fn ($q) => $q->where(fn ($w) => $w
                ->where('ator_rotulo', 'like', '%'.$request->input('busca').'%')
                ->orWhere('nome_informado', 'like', '%'.$request->input('busca').'%')
                ->orWhere('acao', 'like', '%'.$request->input('busca').'%')))
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return Inertia::render('gestao/auditoria', [
            'registros' => $registros->through(fn (AuditLog $r) => [
                'id' => $r->id,
                'quem' => $r->quem(),
                'ator_tipo' => $r->ator_tipo,
                'acao' => $r->acao,
                'entidade' => $r->entidade,
                'entidade_id' => $r->entidade_id,
                'antes' => $r->antes(),
                'depois' => $r->depois(),
                'ip' => $r->ip,
                'hash' => $r->hash,
                'hash_anterior' => $r->hash_anterior,
                'quando' => $r->registrado_em,
            ]),
            'acoes' => AuditLog::query()->selectRaw("SUBSTRING_INDEX(acao, '.', 1) as grupo")->distinct()->orderBy('grupo')->pluck('grupo'),
            'filtro' => $request->only(['acao', 'busca']),
            'verificacao' => session('verificacao'),
        ]);
    }

    public function verificar(Auditoria $auditoria): RedirectResponse
    {
        return back()->with('verificacao', $auditoria->verificar() + ['em' => now()->toIso8601String()]);
    }
}
