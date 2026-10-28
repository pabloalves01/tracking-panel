<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SelectOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'uuid.required' => 'Escolha uma organização',
            'uuid.uuid' => 'Organização inválida',
        ];
    }
}
