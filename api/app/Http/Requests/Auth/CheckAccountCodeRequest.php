<?php

namespace App\Http\Requests\Auth;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

class CheckAccountCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Text::email($this->input('email')),
            'code' => is_scalar($this->input('code')) ? preg_replace('/\D/', '', (string) $this->input('code')) : '',
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório',
            'email.email' => 'Informe um e-mail válido',
            'code.required' => 'O código é obrigatório',
            'code.digits' => 'Informe o código de 6 dígitos',
        ];
    }
}
