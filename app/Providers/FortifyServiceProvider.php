<?php

namespace App\Providers;

use App\Models\Unidade;
use App\Models\User;
use App\Services\Configuracoes;
use App\Support\Aparelho;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurarAutenticacao();
        $this->configurarTelas();
        $this->configurarLimites();
    }

    private function configurarAutenticacao(): void
    {
        // Unidade: login = número do apartamento. Síndico e conselho: login = e-mail.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = Str::lower(trim((string) $request->input('login')));
            $user = User::query()->where('login', $login)->first();

            if ($user && $user->ativo && Hash::check((string) $request->input('password'), $user->password)) {
                return $user;
            }

            return null;
        });

        Passkeys::authorizeLoginUsing(
            fn (Request $request, PasskeyUser $user, Passkey $passkey): bool => $user instanceof User && $user->ativo,
        );
    }

    private function configurarTelas(): void
    {
        Fortify::loginView(function (Request $request) {
            $lembrado = Aparelho::lembrado($request);
            $unidade = $lembrado ? Unidade::query()->where('numero', $lembrado['apto'])->first() : null;

            return Inertia::render('auth/entrar', [
                'edificio' => app(Configuracoes::class)->get('nome_edificio'),
                'apartamentos' => Unidade::query()->where('ativa', true)
                    ->orderByDesc('andar')->orderBy('numero')
                    ->get(['numero', 'andar'])
                    ->groupBy('andar')
                    ->map(fn ($andar) => $andar->pluck('numero')->values())
                    ->values(),
                'lembrado' => $unidade ? [
                    'apto' => $unidade->numero,
                    'andar' => $unidade->andar,
                    'nome' => $lembrado['nome'],
                ] : null,
                'entradaPorBiometria' => $unidade !== null && Aparelho::entradaPorBiometria($request),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/dois-fatores'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirmar-senha'));
    }

    private function configurarLimites(): void
    {
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        // 5 tentativas a cada 15 minutos por apartamento (ou e-mail) + IP.
        RateLimiter::for('login', function (Request $request) {
            $chave = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            // Estourou: volta ao formulário com a mensagem no campo, não a página de erro 429 do Inertia.
            return Limit::perMinutes(15, 5)->by($chave)->response(function (Request $request, array $headers) {
                event(new Lockout($request));
                $minutos = (int) ceil(((int) ($headers['Retry-After'] ?? 900)) / 60);

                return back()->withErrors(['login' => "Muitas tentativas. Tente de novo em {$minutos} min."])->onlyInput('login');
            });
        });

        RateLimiter::for('passkeys', fn (Request $request) => Limit::perMinute(10)->by(
            ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
        ));
    }
}
