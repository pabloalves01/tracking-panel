<?php

namespace App\Http\Requests\Registry;

use App\Rules\Cnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'cnpj' => [
                'bail',
                'required',
                'string',
                new Cnpj,
                Rule::exists('fuel_stations', 'cnpj')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
            'cnpj.exists' => 'Este posto não está cadastrado na Logiv.',
        ];
    }
}
