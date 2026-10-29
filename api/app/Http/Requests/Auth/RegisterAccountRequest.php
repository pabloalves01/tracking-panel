<?php

namespace App\Http\Requests\Auth;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sem regra de e-mail único, de propósito: um 422 "e-mail já cadastrado"
 * diria a existência da conta a quem não provou ler aquela caixa. Quem
 * decide é o serviço, depois de conferir o código.
 */
class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Text::email($this->input('email')),
            'name' => is_scalar($this->input('name')) ? trim((string) $this->input('name')) : '',
            'code' => is_scalar($this->input('code')) ? preg_replace('/\D/', '', (string) $this->input('code')) : '',
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório',
            'email.email' => 'Informe um e-mail válido',
            'code.required' => 'O código é obrigatório',
            'code.digits' => 'Informe o código de 6 dígitos',
            'name.required' => 'O nome é obrigatório',
            'password.required' => 'A senha é obrigatória',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'A confirmação da senha não confere',
        ];
    }
}
