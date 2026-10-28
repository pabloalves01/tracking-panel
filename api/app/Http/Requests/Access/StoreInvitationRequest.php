<?php

namespace App\Http\Requests\Access;

use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => Text::email($this->input('email'))]);
        }
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
            'email.required' => 'Informe o e-mail de quem você quer convidar',
            'email.email' => 'Informe um e-mail válido',
        ];
    }
}
