<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Auditoria\Auditoria;
use App\Services\Configuracoes;
use App\Support\Dinheiro;
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
            'dominio_faturas' => config('condominio.dominio_faturas'),
        ]);
    }

    public function update(Request $request, Configuracoes $configuracoes, Auditoria $auditoria): RedirectResponse
    {
        $dados = $request->validate([
            'nome_edificio' => ['required', 'string', 'max:80'],
            'endereco' => ['nullable', 'string', 'max:160'],
            'contribuicao' => ['required', 'string'],
            'dias_antecedencia' => ['required', 'integer', 'min:0', 'max:25'],
            'regra_rateio' => ['required', 'in:igual,fracao'],
            'pix_chave' => ['nullable', 'string', 'max:77'],
            'pix_recebedor' => ['nullable', 'string', 'max:60'],
            'pix_cidade' => ['nullable', 'string', 'max:30'],
            'banco_titular' => ['nullable', 'string', 'max:120'],
            'limite_anomalia_percentual' => ['required', 'integer', 'min:5', 'max:300'],
            'remetentes_energisa' => ['nullable', 'string', 'max:300'],
            'remetentes_cagepa' => ['nullable', 'string', 'max:300'],
        ]);

        $lista = fn (?string $texto) => array_values(array_filter(array_map(fn ($d) => strtolower(trim($d)), explode(',', (string) $texto))));

        $novos = [
            'nome_edificio' => $dados['nome_edificio'],
            'endereco' => $dados['endereco'],
            'contribuicao_centavos' => Dinheiro::paraCentavos($dados['contribuicao']),
            'dias_antecedencia' => (int) $dados['dias_antecedencia'],
            'regra_rateio' => $dados['regra_rateio'],
            'pix_chave' => $dados['pix_chave'],
            'pix_recebedor' => $dados['pix_recebedor'],
            'pix_cidade' => $dados['pix_cidade'],
            'banco_titular' => $dados['banco_titular'],
            'limite_anomalia_percentual' => (int) $dados['limite_anomalia_percentual'],
            'remetentes_energisa' => $lista($dados['remetentes_energisa'] ?? ''),
            'remetentes_cagepa' => $lista($dados['remetentes_cagepa'] ?? ''),
        ];

        $antes = $configuracoes->salvar($novos);

        if ($antes !== []) {
            $auditoria->registrar('configuracao.alterada', 'configuracoes', $antes, array_intersect_key($novos, $antes));
        }

        return back()->with('sucesso', 'Configurações salvas.');
    }
}
