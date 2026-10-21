<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecuperacaoSenhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:120'],
            'canal' => [$this->routeIs('password.enviar') ? 'required' : 'prohibited', Rule::in(['email', 'whatsapp'])],
        ];
    }
}
