<?php

namespace App\Http\Requests\Registry\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O cliente não tem campo próprio. Dados da pessoa são alterados na entidade.
     */
    public function rules(): array
    {
        return [];
    }
}
