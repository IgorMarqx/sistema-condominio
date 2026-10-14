<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SomenteUnidade
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isUnidade()) {
            return redirect()->route('gestao.painel');
        }

        return $next($request);
    }
}
