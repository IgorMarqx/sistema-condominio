<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Aparelho;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AparelhoController extends Controller
{
    /**
     * "Trocar ×" / "Não é o 302? Trocar apartamento": esquece o aparelho.
     */
    public function esquecer(): RedirectResponse
    {
        Aparelho::esquecer();

        return redirect()->route('login');
    }

    /**
     * Biometria cadastrada neste aparelho: a próxima entrada abre direto no botão de biometria.
     */
    public function biometria(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isUnidade() && $user->hasPasskeysEnabled()) {
            $lembrado = Aparelho::lembrado($request);
            Aparelho::lembrar($user->login, $lembrado['nome'] ?? $request->session()->get('nome_informado'), true);
        }

        return response()->json(['ok' => true]);
    }
}
