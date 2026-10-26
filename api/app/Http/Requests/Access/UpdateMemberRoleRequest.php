<?php

namespace App\Http\Requests\Access;

use App\Support\Membership;
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
            'role' => ['required', Rule::in(Membership::roles())],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'O papel é obrigatório',
            'role.in' => 'Papel inválido',
        ];
    }
}
