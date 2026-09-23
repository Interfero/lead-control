<?php

namespace App\Support;

final class TrustedProxies
{
    /**
     * @return array<int, string>|string
     */
    public static function resolve(): array|string
    {
        $raw = trim((string) env('TRUSTED_PROXIES', '*'));
        if ($raw === '' || $raw === '*') {
            return '*';
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $parts === [] ? '*' : $parts;
    }
}
