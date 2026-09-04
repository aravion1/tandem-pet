<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

final class PhoneService
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        if (! preg_match('/^7\d{10}$/', $digits)) {
            throw ValidationException::withMessages(['phone' => 'Укажите корректный номер телефона РФ.']);
        }

        return '+'.$digits;
    }

    public function hash(string $normalizedPhone): string
    {
        return hash('sha256', $normalizedPhone);
    }

    public function encrypt(string $normalizedPhone): string
    {
        return Crypt::encryptString($normalizedPhone);
    }
}
