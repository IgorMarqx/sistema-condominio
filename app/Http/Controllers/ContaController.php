<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContatosRecuperacaoRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\Auditoria\Auditoria;
use App\Services\RecuperacaoSenhaService;
use App\Support\Aparelho;
use App\Support\ContatoRecuperacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Conta e segurança: senha, biometria (passkeys), 2FA do síndico e e-mail de avisos da unidade.
 */
class ContaController extends Controller
{
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $user = $request->user();

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();
        }

        return Inertia::render('conta', [
            'biometria_aparelho' => Aparelho::lembrado($request)['biometria'] ?? false,
            'passkeys' => $user->passkeys()
                ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                ->latest()
                ->get()
                ->map(fn ($passkey) => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'authenticator' => $passkey->authenticator,
                    'criada_em' => $passkey->created_at->toIso8601String(),
                    'usada_em' => $passkey->last_used_at?->toIso8601String(),
                ])
                ->values(),
            'dois_fatores' => [
                'ativo' => $user->hasEnabledTwoFactorAuthentication(),
                'confirmado' => $user->two_factor_confirmed_at !== null,
                'obrigatorio' => $user->isAdmin(),
            ],
            'email_avisos' => $user->unidade?->email_avisos,
            'contatos_recuperacao' => [
                'email_recuperacao' => ContatoRecuperacao::email($user),
                'telefone' => $user->telefone,
            ],
            'nome_informado' => $request->session()->get('nome_informado'),
        ]);
    }

    public function emailAvisos(Request $request, Auditoria $auditoria): RedirectResponse
    {
        abort_unless($request->user()->isUnidade(), 403);
        $dados = $request->validate(['email_avisos' => ['nullable', 'email', 'max:120']], [], ['email_avisos' => 'e-mail']);

        $unidade = $request->user()->unidade;
        $unidade->update(['email_avisos' => $dados['email_avisos'] ?: null]);
        $auditoria->registrar('unidade.email_avisos', $unidade, null, ['definido' => (bool) $dados['email_avisos']]);

        return back()->with('sucesso', $dados['email_avisos'] ? 'Os avisos também vão para '.$dados['email_avisos'].'.' : 'Avisos só pelo sistema.');
    }

    public function nome(Request $request): RedirectResponse
    {
        $dados = $request->validate(['nome' => ['nullable', 'string', 'max:60']]);
        $nome = trim(strip_tags((string) ($dados['nome'] ?? ''))) ?: null;
        $request->session()->put('nome_informado', $nome);

        $lembrado = Aparelho::lembrado($request);

        if ($lembrado && $lembrado['apto'] === $request->user()->login) {
            Aparelho::lembrar($lembrado['apto'], $nome, $lembrado['biometria']);
        }

        return back()->with('sucesso', $nome ? 'Suas ações aparecem como '.$request->user()->login.' — '.$nome.'.' : 'Nome removido desta sessão.');
    }

    public function recuperacao(ContatosRecuperacaoRequest $request, RecuperacaoSenhaService $service): RedirectResponse
    {
        $service->salvarContatos($request->user(), $request->validated('email_recuperacao'), $request->validated('telefone'));

        return back()->with('sucesso', 'Contatos de recuperação salvos.');
    }
}
