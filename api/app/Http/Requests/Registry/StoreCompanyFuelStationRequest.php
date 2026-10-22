<?php

namespace App\Http\Requests\Registry;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyFuelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Cnpj::digits($this->input('cnpj')),
        ]);
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new Cnpj],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
        ];
    }
}
