<?php

namespace App\Http\Requests\System\Company;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->input('user');

        if (! is_array($user) || ! is_string($user['email'] ?? null)) {
            return;
        }

        $user['email'] = strtolower(trim($user['email']));

        $this->merge([
            'user' => $user,
        ]);
    }

    public function rules(): array
    {
        $email = $this->input('user.email');
        $userExists = is_string($email) && User::where('email', $email)->exists();

        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:18', 'unique:companies,cnpj'],
            'user.name' => $userExists
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'user.email' => ['required', 'email', 'max:255'],
            'user.password' => $userExists
                ? ['nullable']
                : ['required', 'string', 'min:8', 'confirmed'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'legal_name.required' => 'A razão social é obrigatória',
            'legal_name.string' => 'A razão social deve ser uma string',
            'legal_name.max' => 'A razão social deve ter no máximo 255 caracteres',
            'trade_name.required' => 'O nome fantasia é obrigatório',
            'trade_name.string' => 'O nome fantasia deve ser uma string',
            'trade_name.max' => 'O nome fantasia deve ter no máximo 255 caracteres',
            'cnpj.required' => 'O CNPJ é obrigatório',
            'cnpj.string' => 'O CNPJ deve ser uma string',
            'cnpj.max' => 'O CNPJ deve ter no máximo 18 caracteres',
            'cnpj.unique' => 'O CNPJ já está em uso',
            'user.name.required' => 'O nome do usuário é obrigatório',
            'user.email.required' => 'O e-mail do usuário é obrigatório',
            'user.email.email' => 'Informe um e-mail válido',
            'user.password.required' => 'A senha é obrigatória',
            'user.password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'user.password.confirmed' => 'A confirmação da senha não confere',
            'state_id.integer' => 'O estado informado é inválido',
            'state_id.exists' => 'Selecione um estado válido',
            'city_id.integer' => 'A cidade informada é inválida',
            'city_id.exists' => 'Selecione uma cidade válida',
        ];
    }
}
