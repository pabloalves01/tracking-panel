<?php

namespace App\Http\Requests\Auth;

use App\Models\VerificationCode;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendVerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Text::email($this->input('email')),
            'purpose' => is_string($this->input('purpose')) ? $this->input('purpose') : '',
            'token' => is_string($this->input('token')) ? $this->input('token') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'purpose' => ['required', Rule::in([
                VerificationCode::STATION_SIGNUP,
                VerificationCode::INVITATION_REGISTER,
                VerificationCode::PASSWORD_RESET,
            ])],
            'token' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório',
            'email.email' => 'Informe um e-mail válido',
            'purpose.in' => 'Não foi possível enviar o código',
        ];
    }
}
