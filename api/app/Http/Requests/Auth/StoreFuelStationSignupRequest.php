<?php

namespace App\Http\Requests\Auth;

use App\Rules\Cnpj;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelStationSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Cnpj::digits($this->input('cnpj')),
            'email' => Text::email($this->input('email')),
            'name' => is_scalar($this->input('name')) ? $this->input('name') : '',
            'code' => is_scalar($this->input('code')) ? preg_replace('/\D/', '', (string) $this->input('code')) : '',
        ]);
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new Cnpj],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
            'email.required' => 'O e-mail é obrigatório',
            'email.email' => 'Informe um e-mail válido',
            'name.required' => 'O nome é obrigatório',
            'password.required' => 'A senha é obrigatória',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'A confirmação da senha não confere',
            'code.required' => 'O código é obrigatório',
            'code.digits' => 'Informe o código de 6 dígitos',
        ];
    }
}
