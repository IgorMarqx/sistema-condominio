<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auditoria\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Troca de senha: obrigatória no primeiro acesso (senha entregue pelo síndico) e opcional depois.
 */
class SenhaController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('auth/trocar-senha', [
            'primeiro_acesso' => $request->user()->deve_trocar_senha,
            'regras' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request, Auditoria $auditoria): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'senha_atual' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:senha_atual'],
        ], [], ['senha_atual' => 'senha atual', 'password' => 'nova senha']);

        $primeiro = $user->deve_trocar_senha;

        $user->forceFill([
            'password' => $request->input('password'),
            'deve_trocar_senha' => false,
            'senha_definida_em' => now(),
        ])->save();

        $auditoria->registrar('acesso.senha_trocada', $user, null, ['primeiro_acesso' => $primeiro]);

        if ($primeiro) {
            return redirect()->route($user->isUnidade() ? 'inicio' : 'gestao.painel')->with('sucesso', 'Senha definida. Bem-vindo!');
        }

        return back()->with('sucesso', 'Senha alterada.');
    }
}
