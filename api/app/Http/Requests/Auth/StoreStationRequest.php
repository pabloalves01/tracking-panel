<?php

namespace App\Http\Requests\Auth;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro de posto na conta logada.
 */
class StoreStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cnpj'))) {
            $this->merge(['cnpj' => Cnpj::digits($this->input('cnpj'))]);
        }
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['bail', 'required', 'string', new Cnpj],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
            'cnpj.string' => 'Informe um CNPJ válido.',
        ];
    }
}
