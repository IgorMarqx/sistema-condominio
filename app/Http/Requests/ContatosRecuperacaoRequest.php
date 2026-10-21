<?php

namespace App\Http\Requests;

use App\Support\ContatoRecuperacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ContatosRecuperacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'email_recuperacao' => ['nullable', 'email', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'senha_atual' => ['required', 'string', 'current_password'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->filled('telefone') && ContatoRecuperacao::celular($this->input('telefone')) === null) {
            throw ValidationException::withMessages(['telefone' => 'Informe um celular com DDD, por exemplo (83) 99999-9999.']);
        }
    }
}
