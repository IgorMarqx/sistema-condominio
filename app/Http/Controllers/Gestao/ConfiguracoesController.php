<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Auditoria\Auditoria;
use App\Services\Configuracoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracoesController extends Controller
{
    public function edit(Configuracoes $configuracoes): Response
    {
        return Inertia::render('gestao/configuracoes', [
            'valores' => $configuracoes->todas(),
        ]);
    }

    public function update(Request $request, Configuracoes $configuracoes, Auditoria $auditoria): RedirectResponse
    {
        $dados = $request->validate([
            'nome_edificio' => ['required', 'string', 'max:80'],
            'endereco' => ['nullable', 'string', 'max:160'],
            'dias_antecedencia' => ['required', 'integer', 'min:0', 'max:25'],
            'pix_chave' => ['nullable', 'string', 'max:77'],
            'pix_recebedor' => ['nullable', 'string', 'max:60'],
            'pix_cidade' => ['nullable', 'string', 'max:30'],
            'banco_titular' => ['nullable', 'string', 'max:120'],
            'limite_anomalia_percentual' => ['required', 'integer', 'min:5', 'max:300'],
        ]);

        $novos = [
            'nome_edificio' => $dados['nome_edificio'],
            'endereco' => $dados['endereco'],
            'dias_antecedencia' => (int) $dados['dias_antecedencia'],
            'pix_chave' => $dados['pix_chave'],
            'pix_recebedor' => $dados['pix_recebedor'],
            'pix_cidade' => $dados['pix_cidade'],
            'banco_titular' => $dados['banco_titular'],
            'limite_anomalia_percentual' => (int) $dados['limite_anomalia_percentual'],
        ];

        $antes = $configuracoes->salvar($novos);

        if ($antes !== []) {
            $auditoria->registrar('configuracao.alterada', 'configuracoes', $antes, array_intersect_key($novos, $antes));
        }

        return back()->with('sucesso', 'Configurações salvas.');
    }
}
