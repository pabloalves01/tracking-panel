<?php

namespace App\Http\Requests\System\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('admin_email'))) {
            $this->merge(['admin_email' => strtolower(trim($this->input('admin_email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:18', 'unique:companies,cnpj'],
            // Quem vai administrar a empresa. Recebe um convite; a Logiv não cria a conta.
            'admin_email' => ['required', 'string', 'email', 'max:255'],
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
            'admin_email.required' => 'O e-mail do administrador é obrigatório',
            'admin_email.string' => 'Informe um e-mail válido',
            'admin_email.email' => 'Informe um e-mail válido',
            'state_id.integer' => 'O estado informado é inválido',
            'state_id.exists' => 'Selecione um estado válido',
            'city_id.integer' => 'A cidade informada é inválida',
            'city_id.exists' => 'Selecione uma cidade válida',
        ];
    }
}
