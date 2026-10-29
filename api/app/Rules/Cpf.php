<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com 11 dígitos e dígitos verificadores válidos.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(self::digits($value))) {
            $fail('Informe um CPF válido');
        }
    }

    public static function digits(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValid(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        $numbers = array_map(intval(...), str_split($digits));

        $first = self::checkDigit($numbers, 9, 10);
        $second = self::checkDigit($numbers, 10, 11);

        return $first === $numbers[9] && $second === $numbers[10];
    }

    /**
     * @param  array<int, int>  $numbers
     */
    private static function checkDigit(array $numbers, int $length, int $weight): int
    {
        $sum = 0;

        for ($index = 0; $index < $length; $index++) {
            $sum += $numbers[$index] * ($weight - $index);
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
