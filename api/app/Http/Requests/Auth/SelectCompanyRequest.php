<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SelectCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('uuid'))) {
            $this->merge(['uuid' => '']);
        }
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid', 'exists:companies,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'uuid.required' => 'Selecione uma empresa',
            'uuid.uuid' => 'A empresa informada é inválida',
            'uuid.exists' => 'A empresa informada não existe',
        ];
    }
}
