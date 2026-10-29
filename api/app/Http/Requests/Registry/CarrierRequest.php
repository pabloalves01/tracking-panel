<?php

namespace App\Http\Requests\Registry;

use App\Enums\AnttCategory;
use App\Support\Text;
use Illuminate\Validation\Rule;

class CarrierRequest extends EntityRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('rntrc')) {
            $this->merge(['rntrc' => filled($this->input('rntrc')) ? Text::digits($this->input('rntrc')) : null]);
        }
    }

    protected function roleRules(): array
    {
        return [
            'rntrc' => ['nullable', 'digits_between:8,12'],
            'antt_category' => ['nullable', 'required_with:rntrc', Rule::enum(AnttCategory::class)],
            'rntrc_expires_on' => ['nullable', 'date'],
        ];
    }

    protected function roleMessages(): array
    {
        return [
            'rntrc.digits_between' => 'O RNTRC deve ter de 8 a 12 dígitos',
            'antt_category.required_with' => 'Informe a categoria na ANTT',
            'antt_category.enum' => 'A categoria na ANTT é inválida',
            'rntrc_expires_on.date' => 'A validade do RNTRC é inválida',
        ];
    }
}
