<?php

namespace App\Http\Middleware;

use App\Models\Notificacao;
use App\Models\User;
use App\Services\Configuracoes;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'edificio' => fn () => app(Configuracoes::class)->get('nome_edificio'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'login' => $user->login,
                    'tipo' => $user->tipo->value,
                    'unidade' => $user->unidade ? [
                        'id' => $user->unidade->id,
                        'numero' => $user->unidade->numero,
                        'andar' => $user->unidade->andar,
                    ] : null,
                    'dois_fatores' => $user->temDoisFatores(),
                    'pode_editar' => $user->isAdmin(),
                ] : null,
                'nome_informado' => $request->hasSession() ? $request->session()->get('nome_informado') : null,
            ],
            'avisos_nao_lidos' => fn () => $user
                ? Notificacao::query()->doUsuario($user)->whereNull('lida_em')->count()
                : 0,
            'flash' => [
                'sucesso' => fn () => $request->session()->get('sucesso'),
                'erro' => fn () => $request->session()->get('erro'),
                'senha_gerada' => fn () => $request->session()->get('senha_gerada'),
            ],
        ];
    }
}
