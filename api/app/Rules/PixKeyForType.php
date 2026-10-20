<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Confere o formato da chave PIX conforme o tipo escolhido.
 */
class PixKeyForType implements DataAwareRule, ValidationRule
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
        if ($value === null || $value === '') {
            return;
        }

        $type = data_get($this->data, 'pix_key_type');
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($type === 'cpf' && strlen($digits) !== 11) {
            $fail('O CPF da chave PIX deve ter 11 dígitos');
        }

        if ($type === 'cnpj' && strlen($digits) !== 14) {
            $fail('O CNPJ da chave PIX deve ter 14 dígitos');
        }

        if ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('Informe um e-mail válido para a chave PIX');
        }

        if ($type === 'phone' && ! in_array(strlen($digits), [10, 11], true)) {
            $fail('O telefone da chave PIX deve ter 10 ou 11 dígitos');
        }

        if ($type === 'random' && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $value)) {
            $fail('A chave aleatória deve estar no formato de UUID');
        }
    }
}
