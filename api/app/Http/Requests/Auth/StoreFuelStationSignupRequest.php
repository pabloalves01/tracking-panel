<?php

namespace App\Http\Requests\Auth;

use App\Rules\Cnpj;
use App\Services\FuelStation\FuelStationSignupService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro público de posto: sempre uma conta nova.
 * Quem já tem conta entra antes e usa `POST /auth/stations`.
 */
class StoreFuelStationSignupRequest extends FormRequest
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

        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['bail', 'required', 'string', new Cnpj],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
            'cnpj.string' => 'Informe um CNPJ válido.',
            'name.required' => 'O nome é obrigatório',
            'name.string' => 'Informe um nome válido',
            'email.required' => 'O e-mail é obrigatório',
            'email.string' => 'Informe um e-mail válido',
            'email.email' => 'Informe um e-mail válido',
            'email.unique' => FuelStationSignupService::EMAIL_TAKEN,
            'password.required' => 'A senha é obrigatória',
            'password.string' => 'Informe uma senha válida',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'A confirmação da senha não confere',
        ];
    }
}
