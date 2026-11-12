<?php

namespace App\Models;

class WhatsappContact extends StoreRecord
{
    protected $casts = ['active' => 'boolean'];

    public static function normalizeNumber(?string $value): ?string
    {
        $number = preg_replace('/\D/', '', $value ?? '');
        if (str_starts_with($number, '08')) {
            $number = '62'.substr($number, 1);
        }

        return preg_match('/^[1-9][0-9]{7,14}$/D', $number) ? $number : null;
    }
}
