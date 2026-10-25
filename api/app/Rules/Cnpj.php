<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CNPJ com 14 dígitos e dígitos verificadores válidos.
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(self::digits($value))) {
            $fail('Informe um CNPJ válido.');
        }
    }

    public static function digits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValid(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits) === 1) {
            return false;
        }

        $numbers = array_map(intval(...), str_split($digits));
        $first = self::checkDigit($numbers, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = self::checkDigit($numbers, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $first === $numbers[12] && $second === $numbers[13];
    }

    /**
     * @param  array<int, int>  $numbers
     * @param  array<int, int>  $weights
     */
    private static function checkDigit(array $numbers, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += $numbers[$index] * $weight;
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
