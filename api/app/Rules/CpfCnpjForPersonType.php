<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com 11 dígitos quando a pessoa é física.
 * CNPJ com 14 dígitos quando a pessoa é jurídica.
 */
class CpfCnpjForPersonType implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';
        $personType = data_get($this->data, 'entity.person_type');

        if ($personType === 'pf' && strlen($digits) !== 11) {
            $fail('O CPF deve ter 11 dígitos');
        }

        if ($personType === 'pj' && strlen($digits) !== 14) {
            $fail('O CNPJ deve ter 14 dígitos');
        }
    }
}
