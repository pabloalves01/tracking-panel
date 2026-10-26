<?php

namespace App\Http\Requests\System\Company;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Convite de administrador feito pela Logiv. O papel é sempre admin.
 */
class StoreCompanyInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório',
            'email.string' => 'Informe um e-mail válido',
            'email.email' => 'Informe um e-mail válido',
        ];
    }
}
