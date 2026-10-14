<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * "Lembrar deste aparelho": cookie com o apartamento, o nome de quem usa e se a biometria foi
 * cadastrada aqui. Não guarda segredo nenhum — o que autentica é a sessão ou a passkey.
 */
final class Aparelho
{
    public const COOKIE = 'condominio_aparelho';

    private const MINUTOS = 60 * 24 * 365 * 2;

    /**
     * @return array{apto: string, nome: string|null, biometria: bool}|null
     */
    public static function lembrado(Request $request): ?array
    {
        $bruto = $request->cookie(self::COOKIE);
        $dados = is_string($bruto) ? json_decode($bruto, true) : null;

        if (! is_array($dados) || ! isset($dados['apto']) || ! preg_match('/^\d{3,4}$/', (string) $dados['apto'])) {
            return null;
        }

        return [
            'apto' => (string) $dados['apto'],
            'nome' => isset($dados['nome']) ? mb_substr((string) $dados['nome'], 0, 60) : null,
            'biometria' => (bool) ($dados['biometria'] ?? false),
        ];
    }

    public static function lembrar(string $apto, ?string $nome, bool $biometria = false): void
    {
        Cookie::queue(Cookie::make(
            self::COOKIE,
            (string) json_encode(['apto' => $apto, 'nome' => $nome, 'biometria' => $biometria]),
            self::MINUTOS,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    public static function esquecer(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * Tela só com o botão de biometria (mockup 1): aparelho lembrado, biometria cadastrada aqui,
     * a unidade ainda tem passkey e o acesso é de celular.
     */
    public static function entradaPorBiometria(Request $request): bool
    {
        $lembrado = self::lembrado($request);

        if (! $lembrado || ! $lembrado['biometria'] || ! self::celular($request)) {
            return false;
        }

        $user = User::query()->where('login', $lembrado['apto'])->where('ativo', true)->first();

        return $user !== null && $user->hasPasskeysEnabled();
    }

    public static function celular(Request $request): bool
    {
        return (bool) preg_match('/Android|iPhone|iPad|iPod|Mobile/i', (string) $request->userAgent());
    }
}
