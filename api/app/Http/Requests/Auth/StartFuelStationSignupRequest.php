<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

class StartFuelStationSignupRequest extends FormRequest
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
        $email = strtolower(trim((string) $this->input('email')));
        $userExists = $email !== '' && User::query()->whereRaw('lower(email) = ?', [$email])->exists();

        return [
            'cnpj' => ['required', 'string', new Cnpj],
            'email' => ['required', 'email', 'max:255'],
            'name' => $userExists
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'password' => $userExists
                ? ['required', 'string']
                : ['required', 'string', 'min:8', 'confirmed'],
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
        ];
    }
}
