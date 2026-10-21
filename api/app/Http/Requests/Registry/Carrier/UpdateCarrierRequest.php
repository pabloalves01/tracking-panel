<?php

namespace App\Http\Requests\Registry\Carrier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A transportadora não tem campo próprio. Dados da pessoa são alterados na entidade.
     */
    public function rules(): array
    {
        return [];
    }
}
