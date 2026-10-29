<?php

namespace App\Rules;

use App\Support\Text;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RENAVAM com 11 dígitos — o antigo, de 9, com zeros à esquerda — e dígito
 * verificador válido.
 */
class Renavam implements ValidationRule
{
    private const WEIGHTS = [3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(Text::digits($value))) {
            $fail('Informe um RENAVAM válido');
        }
    }

    public static function isValid(string $digits): bool
    {
        if ($digits === '' || strlen($digits) > 11) {
            return false;
        }

        $digits = str_pad($digits, 11, '0', STR_PAD_LEFT);

        if (preg_match('/^(\d)\1{10}$/', $digits) === 1) {
            return false;
        }

        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        $check = 11 - ($sum % 11);

        return ($check > 9 ? 0 : $check) === (int) $digits[10];
    }
}
