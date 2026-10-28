<?php

namespace App\Http\Requests\System;

use App\Rules\Cnpj;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('document')) {
            $input['document'] = Text::digits($this->input('document'));
        }

        if ($this->has('owner_email')) {
            $input['owner_email'] = Text::email($this->input('owner_email'));
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'string', 'size:14', new Cnpj],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Informe o CNPJ da empresa',
            'document.size' => 'O CNPJ deve ter 14 dígitos',
            'legal_name.required' => 'Informe a razão social',
            'owner_email.required' => 'Informe o e-mail de quem vai administrar a empresa',
            'owner_email.email' => 'Informe um e-mail válido',
            'state_id.exists' => 'Selecione um estado válido',
            'city_id.exists' => 'Selecione uma cidade válida',
        ];
    }
}
