<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\Concessionaria;
use App\Http\Controllers\Controller;
use App\Models\Medidor;
use App\Models\Unidade;
use App\Services\Auditoria\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MedidoresController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('gestao/medidores', [
            'medidores' => Medidor::query()->with('unidade')
                ->orderByRaw("FIELD(dono_tipo, 'condominio', 'unidade')")->orderBy('unidade_id')->orderBy('concessionaria')
                ->get()
                ->map(fn (Medidor $m) => [
                    'id' => $m->id,
                    'dono_tipo' => $m->dono_tipo,
                    'unidade' => $m->unidade?->numero,
                    'concessionaria' => $m->concessionaria->value,
                    'concessionaria_rotulo' => $m->concessionaria->rotulo(),
                    'identificador' => $m->identificador,
                    'descricao' => $m->descricao,
                    'tem_senha_pdf' => $m->senha_pdf !== null,
                    'ativo' => $m->ativo,
                ]),
            'unidades' => Unidade::query()->ordemFixa()->pluck('numero'),
        ]);
    }

    public function store(Request $request, Auditoria $auditoria): RedirectResponse
    {
        $dados = $this->validar($request);
        $medidor = Medidor::query()->create($dados);
        $auditoria->registrar('medidor.cadastrado', $medidor, null, $this->resumo($medidor));

        return back()->with('sucesso', 'Medidor cadastrado.');
    }

    public function update(Request $request, Medidor $medidor, Auditoria $auditoria): RedirectResponse
    {
        $dados = $this->validar($request, $medidor);
        $antes = $this->resumo($medidor);

        if (! $request->filled('senha_pdf') && ! $request->boolean('remover_senha')) {
            unset($dados['senha_pdf']);
        }

        $medidor->update($dados);
        $auditoria->registrar('medidor.atualizado', $medidor, $antes, $this->resumo($medidor->refresh()));

        return back()->with('sucesso', 'Medidor atualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Medidor $medidor = null): array
    {
        $dados = $request->validate([
            'dono_tipo' => ['required', 'in:condominio,unidade'],
            'unidade' => ['required_if:dono_tipo,unidade', 'nullable', 'exists:unidades,numero'],
            'concessionaria' => ['required', Rule::enum(Concessionaria::class)],
            'identificador' => ['required', 'string', 'max:40', Rule::unique('medidores')->where('concessionaria', $request->input('concessionaria'))->ignore($medidor?->id)],
            'descricao' => ['nullable', 'string', 'max:120'],
            'senha_pdf' => ['nullable', 'string', 'max:60'],
            'ativo' => ['boolean'],
        ], [], ['identificador' => 'nº da UC / matrícula']);

        return [
            'dono_tipo' => $dados['dono_tipo'],
            'unidade_id' => $dados['dono_tipo'] === 'unidade' ? Unidade::query()->where('numero', $dados['unidade'])->value('id') : null,
            'concessionaria' => $dados['concessionaria'],
            'identificador' => trim($dados['identificador']),
            'descricao' => $dados['descricao'] ?? null,
            'senha_pdf' => $request->boolean('remover_senha') ? null : ($dados['senha_pdf'] ?? null),
            'ativo' => $dados['ativo'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumo(Medidor $m): array
    {
        return [
            'dono' => $m->isCondominio() ? 'condominio' : 'unidade '.$m->unidade?->numero,
            'concessionaria' => $m->concessionaria->value,
            'identificador' => $m->identificador,
            'ativo' => $m->ativo,
            'senha_pdf' => $m->senha_pdf !== null,
        ];
    }
}
