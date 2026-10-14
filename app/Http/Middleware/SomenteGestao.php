<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Área de gestão: síndico (altera) e conselho (só lê). O síndico precisa do 2FA ativo.
 * Qualquer método que não seja leitura exige o síndico.
 */
class SomenteGestao
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->vePainelGestao()) {
            return redirect()->route('inicio');
        }

        if ($user->isAdmin() && ! $user->temDoisFatores() && ! $request->routeIs('gestao.seguranca*')) {
            return redirect()->route('gestao.seguranca')->with('erro', 'Ative a verificação em duas etapas para usar a gestão.');
        }

        if (! $request->isMethodSafe() && ! $user->isAdmin()) {
            abort(403, 'O conselho tem acesso somente de leitura.');
        }

        return $next($request);
    }
}
