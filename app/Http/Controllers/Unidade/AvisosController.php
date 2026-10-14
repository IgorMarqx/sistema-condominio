<?php

namespace App\Http\Controllers\Unidade;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Avisos da unidade e do síndico (mesma tela; o escopo vem do usuário).
 */
class AvisosController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('avisos', [
            'avisos' => Notificacao::query()->doUsuario($request->user())->latest('id')->limit(100)->get()
                ->map(fn (Notificacao $n) => self::dados($n)),
        ]);
    }

    public function lidas(Request $request): RedirectResponse
    {
        Notificacao::query()->doUsuario($request->user())->whereNull('lida_em')->update(['lida_em' => now()]);

        return back();
    }

    public function abrir(Request $request, Notificacao $aviso): RedirectResponse
    {
        abort_unless(Notificacao::query()->doUsuario($request->user())->whereKey($aviso->id)->exists(), 404);

        $aviso->lida_em ??= now();
        $aviso->save();

        return redirect($aviso->url ?: '/');
    }

    /**
     * @return array<string, mixed>
     */
    public static function dados(Notificacao $n): array
    {
        return [
            'id' => $n->id,
            'tipo' => $n->tipo,
            'titulo' => $n->titulo,
            'mensagem' => $n->mensagem,
            'url' => $n->url,
            'concessionaria' => $n->payload['concessionaria'] ?? null,
            'lida' => $n->lida_em !== null,
            'criado_em' => $n->created_at->toIso8601String(),
        ];
    }
}
