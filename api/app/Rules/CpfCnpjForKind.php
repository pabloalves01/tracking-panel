<?php

namespace App\Rules;

use App\Models\Registry\Entity;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com 11 dígitos quando a entidade é pessoa física.
 * CNPJ com 14 dígitos quando é pessoa jurídica.
 */
class CpfCnpjForKind implements DataAwareRule, ValidationRule
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
        $kind = data_get($this->data, 'kind');

        if ($kind === Entity::INDIVIDUAL && ! Cpf::isValid(Cpf::digits($value))) {
            $fail('Informe um CPF válido');
        }

        if ($kind === Entity::ORGANIZATION && ! Cnpj::isValid(Cnpj::digits($value))) {
            $fail('Informe um CNPJ válido');
        }
    }
}
