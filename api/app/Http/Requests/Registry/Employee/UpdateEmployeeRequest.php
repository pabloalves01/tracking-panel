<?php

namespace App\Http\Requests\Registry\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O funcionário não tem campo próprio. Dados da pessoa são alterados na entidade.
     */
    public function rules(): array
    {
        return [];
    }
}
