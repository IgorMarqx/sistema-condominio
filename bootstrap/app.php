<?php

use App\Http\Middleware\ExigeTrocaSenha;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SomenteGestao;
use App\Http\Middleware\SomenteUnidade;
use App\Support\Aparelho;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O cookie do aparelho não guarda segredo e precisa ser lido antes do login.
        $middleware->encryptCookies(except: [Aparelho::COOKIE]);

        // Webhooks de e-mail chegam de fora, autenticados por assinatura (SNS) ou token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ExigeTrocaSenha::class,
        ]);

        $middleware->alias([
            'unidade' => SomenteUnidade::class,
            'gestao' => SomenteGestao::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/*') || $request->expectsJson(),
        );
    })->create();
