<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyFuelStationSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código é obrigatório',
            'code.digits' => 'O código deve ter 6 dígitos',
        ];
    }
}
