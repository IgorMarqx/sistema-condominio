<?php

namespace App\Http\Requests;

use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Campos digitados de uma fatura (upload manual ou correção na revisão).
 */
class CamposFaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mes_ref' => ['nullable', 'date_format:Y-m'],
            'valor' => ['nullable', 'string', 'regex:/^[\d.,\sR$]+$/'],
            'vencimento' => ['nullable', 'date'],
            'emissao' => ['nullable', 'date'],
            'consumo' => ['nullable', 'numeric', 'min:0'],
            'leitura_anterior' => ['nullable', 'date'],
            'leitura_atual' => ['nullable', 'date'],
            'proxima_leitura' => ['nullable', 'date'],
            'linha_digitavel' => ['nullable', 'string', 'max:80'],
            'pix_copia_cola' => ['nullable', 'string', 'max:1000'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mes_ref' => 'competência',
            'valor' => 'valor',
            'leitura_anterior' => 'leitura anterior',
            'leitura_atual' => 'leitura atual',
            'proxima_leitura' => 'próxima leitura',
            'linha_digitavel' => 'linha digitável',
        ];
    }

    /**
     * Campos no formato do model (valor em centavos), só os preenchidos.
     *
     * @return array<string, mixed>
     */
    public function campos(): array
    {
        $dados = $this->safe()->except(['motivo']);
        $campos = [];

        foreach ($dados as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            if ($chave === 'valor') {
                $campos['valor_centavos'] = Dinheiro::paraCentavos((string) $valor);

                continue;
            }

            $campos[$chave] = $chave === 'mes_ref' ? $valor.'-01' : $valor;
        }

        return $campos;
    }
}
