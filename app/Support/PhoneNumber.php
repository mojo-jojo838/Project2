<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if (! $phone) return null;
        return str_starts_with($phone, '09') ? '+63'.substr($phone, 1) : $phone;
    }
}
