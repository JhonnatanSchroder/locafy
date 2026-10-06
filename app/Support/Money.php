<?php

namespace App\Support;

final class Money
{
    public static function cents(string $amount): int
    {
        $parts = explode('.', $amount, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '0', 2, '0');
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
