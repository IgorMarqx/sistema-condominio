<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * "Lembrar deste aparelho": cookie com o apartamento, o nome de quem usa e se a biometria foi
 * cadastrada aqui; e o e-mail da gestão quando o síndico (ou conselho) cadastrou biometria aqui. Não guarda segredo nenhum — o que autentica é a sessão ou a passkey.
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
        $dados = self::bruto($request);

        if (! isset($dados['apto']) || ! preg_match('/^\d{3,4}$/', (string) $dados['apto'])) {
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
        $atual = self::bruto(request());

        self::gravar([
            ...$atual,
            'apto' => $apto,
            'nome' => $nome,
            'biometria' => $biometria,
            // A tela de biometria abre para quem cadastrou por último neste aparelho.
            'entrada' => $biometria ? 'unidade' : ($atual['entrada'] ?? null),
        ]);
    }

    /**
     * Síndico ou conselho com biometria cadastrada neste aparelho: a próxima entrada abre direto
     * nela, sem senha e sem o código do 2FA.
     */
    public static function lembrarGestao(string $email): void
    {
        self::gravar([...self::bruto(request()), 'gestao' => $email, 'entrada' => 'gestao']);
    }

    /** "Trocar apartamento": esquece só o apartamento; a biometria da gestão continua. */
    public static function esquecer(): void
    {
        $atual = self::bruto(request());
        self::gravar(isset($atual['gestao']) ? ['gestao' => $atual['gestao'], 'entrada' => 'gestao'] : []);
    }

    public static function esquecerGestao(): void
    {
        $atual = self::bruto(request());
        unset($atual['gestao']);
        $atual['entrada'] = ($atual['biometria'] ?? false) ? 'unidade' : null;
        self::gravar($atual);
    }

    /** E-mail da gestão lembrado com biometria neste aparelho. */
    public static function gestao(Request $request): ?string
    {
        $email = self::bruto($request)['gestao'] ?? null;

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Tela de biometria da gestão: celular, último cadastro de biometria daqui foi da gestão e a
     * conta ainda tem passkey.
     */
    public static function entradaPorBiometriaGestao(Request $request): ?User
    {
        $email = self::gestao($request);

        if (! $email || (self::bruto($request)['entrada'] ?? null) !== 'gestao' || ! self::celular($request)) {
            return null;
        }

        $user = User::query()->where('login', $email)->where('ativo', true)->first();

        return $user !== null && $user->vePainelGestao() && $user->hasPasskeysEnabled() ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bruto(Request $request): array
    {
        $bruto = $request->cookie(self::COOKIE);
        $dados = is_string($bruto) ? json_decode($bruto, true) : null;

        return is_array($dados) ? $dados : [];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private static function gravar(array $dados): void
    {
        if ($dados === []) {
            Cookie::queue(Cookie::forget(self::COOKIE));

            return;
        }

        Cookie::queue(Cookie::make(
            self::COOKIE,
            (string) json_encode($dados),
            self::MINUTOS,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /**
     * Tela só com o botão de biometria (mockup 1): aparelho lembrado, biometria cadastrada aqui,
     * a unidade ainda tem passkey e o acesso é de celular.
     */
    public static function entradaPorBiometria(Request $request): bool
    {
        $lembrado = self::lembrado($request);

        if (! $lembrado || ! $lembrado['biometria'] || ! self::celular($request) || (self::bruto($request)['entrada'] ?? 'unidade') === 'gestao') {
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
