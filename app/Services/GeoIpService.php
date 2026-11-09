<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;

/** IP → страна/регион/город по локальной базе DB-IP Lite. Нет базы или IP не найден — null, вход не ломается. */
class GeoIpService
{
    private ?Reader $reader = null;

    private bool $failed = false;

    /** @return array{country: ?string, region: ?string, city: ?string}|null */
    public function lookup(?string $ip): ?array
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        $reader = $this->reader();
        if ($reader === null) {
            return null;
        }
        try {
            $r = $reader->get($ip);
        } catch (\Throwable $e) {
            Log::warning('geoip lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);

            return null;
        }
        if (! is_array($r)) {
            return null;
        }

        return [
            'country' => $r['country']['iso_code'] ?? null,
            'region' => $r['subdivisions'][0]['names']['en'] ?? null,
            'city' => $r['city']['names']['en'] ?? null,
        ];
    }

    public function isAlertRegion(?array $geo): bool
    {
        $region = mb_strtolower((string) ($geo['region'] ?? ''));
        if ($region === '') {
            return false;
        }
        foreach ((array) config('geoip.alert_regions', []) as $needle) {
            if ($needle !== '' && str_contains($region, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function reader(): ?Reader
    {
        if ($this->reader || $this->failed) {
            return $this->reader;
        }
        $path = (string) config('geoip.database');
        try {
            if (! is_file($path)) {
                throw new \RuntimeException('база не найдена: '.$path);
            }
            $this->reader = new Reader($path);
        } catch (\Throwable $e) {
            $this->failed = true;
            Log::warning('geoip database unavailable', ['error' => $e->getMessage()]);
        }

        return $this->reader;
    }
}
