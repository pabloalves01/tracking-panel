<?php

namespace App\Http\Requests\Registry;

use App\Models\Company;
use App\Rules\Cnpj;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowCompanyFuelStationRequest extends FormRequest
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
                Rule::notIn([Company::query()->whereKey(app(CurrentCompany::class)->id)->value('cnpj')]),
                Rule::exists('fuel_stations', 'cnpj')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'O CNPJ é obrigatório',
            'cnpj.not_in' => 'Você não pode convidar o próprio CNPJ.',
            'cnpj.exists' => 'Este posto não está cadastrado na Logiv.',
        ];
    }
}
