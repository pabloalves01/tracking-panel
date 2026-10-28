<?php

namespace App\Http\Requests\Registry;

use App\Rules\Cnpj;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelSupplyLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document')) {
            $this->merge(['document' => Text::digits($this->input('document'))]);
        }
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'string', 'size:14'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Informe o CNPJ do posto',
            'document.size' => 'O CNPJ deve ter 14 dígitos',
        ];
    }
}
