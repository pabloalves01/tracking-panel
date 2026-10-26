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
        $personType = data_get($this->data, 'entity.person_type');

        if ($personType === 'pf' && ! Cpf::isValid(Cpf::digits($value))) {
            $fail('Informe um CPF válido');
        }

        if ($personType === 'pj' && ! Cnpj::isValid(Cnpj::digits($value))) {
            $fail('Informe um CNPJ válido');
        }
    }
}
