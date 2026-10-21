<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SelectFuelStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid', 'exists:fuel_stations,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'uuid.required' => 'Selecione um posto',
            'uuid.uuid' => 'O posto informado é inválido',
            'uuid.exists' => 'O posto informado não existe',
        ];
    }
}
