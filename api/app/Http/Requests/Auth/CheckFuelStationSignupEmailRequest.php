<?php

namespace App\Http\Requests\Auth;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

class CheckFuelStationSignupEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Cnpj::digits($this->input('cnpj')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new Cnpj],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
            'email.required' => 'O e-mail é obrigatório',
            'email.email' => 'Informe um e-mail válido',
        ];
    }
}
