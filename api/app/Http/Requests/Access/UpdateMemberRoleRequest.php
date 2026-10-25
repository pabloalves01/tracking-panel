<?php

namespace App\Http\Requests\Access;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Invitation::ROLES)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Escolha o papel',
            'role.in' => 'Escolha Administrador ou Membro',
        ];
    }
}
