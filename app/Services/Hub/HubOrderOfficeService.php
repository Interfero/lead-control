<?php

namespace App\Services\Hub;

use App\Models\Hub\HubOrderCache;
use App\Support\OrderAddressReveal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Квартира KP для GM: кэш Desk address_office, иначе один reveal через Desk internal
 * (тот же get-address-office, что кнопка «Показать квартиру»).
 */
class HubOrderOfficeService
{
    /** @var array<string, string|null> memo на запрос: external_id → text|null */
    private array $memo = [];

    /**
     * Если окно открыто и кэша нет — при $fetchMissing дергает Desk один раз;
     * пишет text в $row (in-memory). Ошибки не роняют list/detail.
     *
     * Для list передавайте $fetchMissing=false: только кэш (иначе N×KP unlock
     * и риск исчерпать PHP-FPM на LC↔Desk loopback).
     */
    public function ensureOffice(HubOrderCache $row, bool $fetchMissing = true): void
    {
        $existing = trim((string) ($row->address_office ?? ''));
        if ($existing !== '') {
            return;
        }

        $tz = trim((string) ($row->timezone ?? '')) ?: null;
        if (! OrderAddressReveal::canRevealForMaster($row->call_at_local, $row->raw_status, $tz)) {
            return;
        }

        if (! $fetchMissing) {
            return;
        }

        $externalId = (string) $row->external_id;
        if (array_key_exists($externalId, $this->memo)) {
            if ($this->memo[$externalId] !== null && $this->memo[$externalId] !== '') {
                $row->address_office = $this->memo[$externalId];
            }

            return;
        }

        $text = $this->revealViaDesk($externalId);
        $this->memo[$externalId] = $text;
        if ($text !== null && $text !== '') {
            $row->address_office = $text;
        }
    }

    private function revealViaDesk(string $externalId): ?string
    {
        $driver = (string) config('services.unified_hub.write_driver', 'http');
        if ($driver === 'cache') {
            // Тесты / dry-run: только то, что уже в orders_cache (без HTTP в КП).
            return null;
        }

        $base = rtrim((string) config('services.unified_hub.write_url', ''), '/');
        $token = (string) config('services.unified_hub.write_token', '');
        if ($base === '' || $token === '') {
            return null;
        }

        $url = $base.'/desk/internal/orders/'.$externalId.'/reveal-office';

        try {
            $timeout = min(20, (int) config('services.unified_hub.write_timeout', 45));
            $response = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post($url);
        } catch (Throwable $exception) {
            Log::warning('hub.office.desk_http_failed', [
                'external_id' => $externalId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $json = $response->json() ?? [];
        if ($response->successful() && ($json['ok'] ?? false)) {
            $text = trim((string) ($json['text'] ?? ''));

            return $text !== '' ? $text : null;
        }

        // 403 вне окна / 404 нет кв — нормальные исходы, не алертим как сбой записи.
        if (! in_array($response->status(), [403, 404], true)) {
            Log::info('hub.office.desk_reveal_failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
                'code' => $json['code'] ?? null,
            ]);
        }

        return null;
    }
}
