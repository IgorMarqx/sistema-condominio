<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecuperacaoSenhaRequest;
use App\Http\Requests\RedefinirSenhaRequest;
use App\Services\RecuperacaoSenhaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RecuperacaoSenhaController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/recuperar-senha');
    }

    public function canais(RecuperacaoSenhaRequest $request, RecuperacaoSenhaService $service): JsonResponse
    {
        return response()->json(['canais' => $service->canais($request->validated('identificador'))]);
    }

    public function enviar(RecuperacaoSenhaRequest $request, RecuperacaoSenhaService $service): JsonResponse
    {
        return response()->json($service->enviar($request->validated('identificador'), $request->validated('canal')));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('auth/redefinir-senha', [
            'token' => $token,
            'login' => $request->string('login')->toString(),
            'regras' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(RedefinirSenhaRequest $request, RecuperacaoSenhaService $service): RedirectResponse
    {
        $service->redefinir(
            $request->validated('login'),
            $request->validated('token'),
            $request->validated('password'),
        );

        return redirect()->route('login')->with('sucesso', 'Senha alterada. Entre com a nova senha.');
    }
}
