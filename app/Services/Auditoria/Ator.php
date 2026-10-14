<?php

namespace App\Services\Auditoria;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Quem está agindo agora: usuário logado (com o nome informado na sessão) ou o sistema.
 */
final readonly class Ator
{
    public function __construct(
        public string $tipo,
        public ?int $id,
        public ?string $rotulo,
        public ?string $nomeInformado,
        public ?string $ip,
    ) {}

    public static function atual(): self
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return self::sistema();
        }

        $request = request();
        $nome = $request->hasSession() ? $request->session()->get('nome_informado') : null;

        return new self(
            tipo: $user->tipo->value,
            id: $user->id,
            rotulo: self::rotular($user),
            nomeInformado: $nome,
            ip: $request->ip(),
        );
    }

    public static function sistema(): self
    {
        return new self('sistema', null, 'Sistema', null, null);
    }

    public static function rotular(User $user): string
    {
        return match (true) {
            $user->isUnidade() => (string) $user->login,
            $user->isAdmin() => $user->nome.' (síndico)',
            default => $user->nome.' (conselho)',
        };
    }
}
