<?php

namespace App\Services;

use Illuminate\Support\Str;

class RegistrationIdentityService
{
    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function normalizeMobile(string $input): string
    {
        $input = trim($input);
        if (! preg_match('/^\+?[0-9\s]+$/u', $input)) {
            return $input;
        }

        $digits = preg_replace('/\s+/', '', $input) ?? '';
        $digits = str_starts_with($digits, '+') ? substr($digits, 1) : $digits;

        if (str_starts_with($digits, '63')) {
            $national = substr($digits, 2);
            $national = str_starts_with($national, '0') ? substr($national, 1) : $national;

            return preg_match('/^9\d{9}$/', $national) ? '0'.$national : $input;
        }

        if (preg_match('/^09\d{9}$/', $digits)) {
            return $digits;
        }

        return preg_match('/^9\d{9}$/', $digits) ? '0'.$digits : $input;
    }

    public function canonicalText(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }

    public function canonicalDigits(string $value): string
    {
        return preg_replace('/\D/u', '', $value) ?? '';
    }

    public function canonicalCode(string $value): string
    {
        return Str::upper(trim($value));
    }

    public function canonicalPlate(string $value): string
    {
        return Str::upper(preg_replace('/[\s-]+/u', '', trim($value)) ?? trim($value));
    }

    public function nameRule(): string
    {
        return '/\A(?=.*[\p{L}\p{M}])[\p{L}\p{M} .\x27-]+\z/u';
    }
}
