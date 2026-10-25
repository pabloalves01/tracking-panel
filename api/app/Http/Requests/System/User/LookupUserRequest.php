<?php

namespace App\Http\Requests\System\User;

use Illuminate\Foundation\Http\FormRequest;

class LookupUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->query('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail',
            'email.email' => 'Informe um e-mail válido',
        ];
    }
}
