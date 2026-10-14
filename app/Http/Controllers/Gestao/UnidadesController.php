<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\SessaoLog;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Support\SenhaAleatoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UnidadesController extends Controller
{
    public function index(): Response
    {
        $unidades = Unidade::query()->with('acesso')->withCount('medidores')->ordemFixa()->get();

        return Inertia::render('gestao/unidades', [
            'unidades' => $unidades->map(fn (Unidade $u) => [
                'id' => $u->id,
                'numero' => $u->numero,
                'andar' => $u->andar,
                'fracao_ideal' => $u->fracao_ideal !== null ? (float) $u->fracao_ideal : null,
                'ativa' => $u->ativa,
                'medidores' => $u->medidores_count,
                'acesso' => $u->acesso ? [
                    'deve_trocar_senha' => $u->acesso->deve_trocar_senha,
                    'ultimo_acesso_em' => $u->acesso->ultimo_acesso_em?->toIso8601String(),
                    'biometria' => $u->acesso->passkeys()->count(),
                    'ativo' => $u->acesso->ativo,
                ] : null,
            ]),
            'soma_fracoes' => (float) $unidades->where('ativa', true)->sum('fracao_ideal'),
            'acessos' => SessaoLog::query()->with('user')->latest('id')->limit(40)->get()->map(fn (SessaoLog $s) => [
                'id' => $s->id,
                'quem' => $s->user->isUnidade() ? $s->user->login.($s->nome_informado ? ' — '.$s->nome_informado : '') : $s->user->nome,
                'metodo' => $s->metodo,
                'ip' => $s->ip,
                'aparelho' => self::aparelho((string) $s->user_agent),
                'quando' => $s->criado_em->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Gera a senha nova (16 caracteres), mostra uma vez só e obriga a troca no primeiro acesso.
     */
    public function senha(Request $request, Unidade $unidade, Auditoria $auditoria): RedirectResponse
    {
        $senha = SenhaAleatoria::gerar();

        DB::transaction(function () use ($unidade, $senha, $auditoria) {
            $user = $unidade->acesso ?? new User([
                'nome' => 'Apartamento '.$unidade->numero,
                'login' => $unidade->numero,
                'tipo' => 'unidade',
                'unidade_id' => $unidade->id,
            ]);

            $user->forceFill([
                'password' => $senha,
                'deve_trocar_senha' => true,
                'senha_definida_em' => now(),
                'ativo' => true,
            ])->save();

            // Senha nova derruba as sessões abertas e a biometria cadastrada com a senha antiga.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->passkeys()->delete();
            $user->forceFill(['remember_token' => null])->save();

            $auditoria->registrar('acesso.senha_redefinida', $user, null, ['unidade' => $unidade->numero]);
        });

        return back()->with('senha_gerada', ['apto' => $unidade->numero, 'senha' => $senha]);
    }

    public function update(Request $request, Unidade $unidade, Auditoria $auditoria): RedirectResponse
    {
        $dados = $request->validate([
            'fracao_ideal' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ativa' => ['required', 'boolean'],
        ]);

        $antes = ['fracao_ideal' => $unidade->fracao_ideal, 'ativa' => $unidade->ativa];
        $unidade->update($dados);
        $auditoria->registrar('unidade.atualizada', $unidade, $antes, $dados);

        return back()->with('sucesso', 'Apto '.$unidade->numero.' atualizado.');
    }

    public static function aparelho(string $ua): string
    {
        $sistema = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Outro',
        };

        $navegador = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'SamsungBrowser') => 'Samsung',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => '',
        };

        return trim($sistema.' · '.$navegador, ' ·');
    }
}
