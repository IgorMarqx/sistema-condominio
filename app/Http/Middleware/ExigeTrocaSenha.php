<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Senha entregue pelo síndico vale só para o primeiro acesso.
 */
class ExigeTrocaSenha
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->deve_trocar_senha && ! $request->routeIs('senha.*', 'logout')) {
            return redirect()->route('senha.trocar');
        }

        return $next($request);
    }
}
