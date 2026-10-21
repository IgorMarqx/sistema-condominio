<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class ContatoRecuperacao
{
    public static function email(User $user): ?string
    {
        $email = $user->email_recuperacao ?: $user->email;

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    public static function celular(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', $telefone ?? '') ?? '';

        if (strlen($digitos) === 13 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        return preg_match('/^[1-9]{2}9\d{8}$/', $digitos) === 1 ? $digitos : null;
    }

    /** @return array{email: string|null, whatsapp: string|null} */
    public static function canais(User $user): array
    {
        $email = self::email($user);
        $celular = self::celular($user->telefone);

        return [
            'email' => $email === null ? null : self::mascararEmail($email),
            'whatsapp' => $celular === null ? null : '('.substr($celular, 0, 2).') •••••-'.substr($celular, -4),
        ];
    }

    private static function mascararEmail(string $email): string
    {
        [$local, $dominio] = explode('@', $email, 2);

        return Str::substr($local, 0, min(2, max(1, Str::length($local) - 1))).'•••@'.$dominio;
    }
}
