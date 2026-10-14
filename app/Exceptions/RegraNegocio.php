<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Regra de negócio violada: volta para a tela com a mensagem no toast, sem 500.
 */
class RegraNegocio extends RuntimeException
{
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->with('erro', $this->getMessage());
    }
}
