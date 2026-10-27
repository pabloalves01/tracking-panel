<?php

namespace App\Support;

class Text
{
    public static function plain(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    public static function email(mixed $value): string
    {
        return strtolower(self::plain($value));
    }

    public static function digits(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
