<?php

namespace App\Listeners;

use App\Models\SessaoLog;
use App\Models\User;
use App\Services\Auditoria\Ator;
use App\Services\Auditoria\Auditoria;
use App\Support\Aparelho;
use Illuminate\Auth\Events\Login;

/**
 * Cada entrada vira registro em sessoes_log e na auditoria. Como o login da unidade é
 * compartilhado, o nome informado ("Maria") acompanha a sessão e aparece em "302 — Maria".
 */
class RegistrarAcesso
{
    public function __construct(private readonly Auditoria $auditoria) {}

    public function handle(Login $evento): void
    {
        $user = $evento->user;

        if (! $user instanceof User) {
            return;
        }

        $request = request();
        $biometria = $request->routeIs('passkey.login');
        $lembrado = Aparelho::lembrado($request);

        $nome = $biometria
            ? ($lembrado && $lembrado['apto'] === $user->login ? $lembrado['nome'] : null)
            : $this->nomeDigitado($request->input('nome'));

        if ($request->hasSession()) {
            $request->session()->put('nome_informado', $user->isUnidade() ? $nome : null);
        }

        SessaoLog::query()->create([
            'user_id' => $user->id,
            'unidade_id' => $user->unidade_id,
            'nome_informado' => $user->isUnidade() ? $nome : null,
            'metodo' => $biometria ? 'biometria' : 'senha',
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'criado_em' => now(),
        ]);

        $user->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();

        if ($user->isUnidade()) {
            if ($biometria || $request->boolean('lembrar')) {
                // A biometria do aparelho continua valendo se já era deste apartamento.
                $biometriaAqui = $biometria || ($lembrado !== null && $lembrado['biometria'] && $lembrado['apto'] === $user->login);
                Aparelho::lembrar($user->login, $nome, $biometriaAqui);
            } elseif ($request->has('lembrar')) {
                Aparelho::esquecer();
            }
        }

        $this->auditoria->registrar('acesso.login', $user, null, ['metodo' => $biometria ? 'biometria' : 'senha'], new Ator(
            tipo: $user->tipo->value,
            id: $user->id,
            rotulo: Ator::rotular($user),
            nomeInformado: $user->isUnidade() ? $nome : null,
            ip: $request->ip(),
        ));
    }

    private function nomeDigitado(mixed $nome): ?string
    {
        $nome = trim(strip_tags((string) $nome));

        return $nome === '' ? null : mb_substr($nome, 0, 60);
    }
}
