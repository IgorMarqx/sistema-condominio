<?php

namespace Database\Factories;

use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'nome' => fake()->name(),
            'login' => $email,
            'email' => $email,
            'tipo' => 'admin',
            'password' => static::$password ??= Hash::make('password'),
            'deve_trocar_senha' => false,
            'ativo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function comDoisFatores(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('segredo'),
            'two_factor_recovery_codes' => encrypt(json_encode(['codigo-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function conselho(): static
    {
        return $this->state(fn () => ['tipo' => 'conselho']);
    }

    public function daUnidade(Unidade $unidade): static
    {
        return $this->state(fn () => [
            'nome' => 'Apartamento '.$unidade->numero,
            'login' => $unidade->numero,
            'email' => null,
            'tipo' => 'unidade',
            'unidade_id' => $unidade->id,
        ]);
    }
}
