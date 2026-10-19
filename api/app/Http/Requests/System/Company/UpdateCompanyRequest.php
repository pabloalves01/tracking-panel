<?php

namespace App\Http\Requests\System\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['required', 'string', 'max:255'],
            'cnpj' => [
                'required',
                'string',
                'max:18',
                // Ignora a própria empresa para não acusar o CNPJ como duplicado dela mesma.
                Rule::unique('companies', 'cnpj')->ignore($this->route('uuid'), 'uuid'),
            ],
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
        ];
    }
}
