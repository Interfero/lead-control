<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Доставка outbox в SuperPart с HMAC timestamp+event_id+body_hash (FR-SYNC-05).
 */
class SuperpartOutboxDeliverer
{
    public function deliverPending(int $limit = 50): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        $rows = DB::table('superpart_outbox')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $ok = $this->deliverRow($row);
            $stats[$ok ? 'sent' : 'failed']++;
        }

        return $stats;
    }

    public function deliverEventId(string $eventId): bool
    {
        $row = DB::table('superpart_outbox')->where('event_id', $eventId)->first();
        if (! $row) {
            return false;
        }

        return $this->deliverRow($row);
    }

    private function deliverRow(object $row): bool
    {
        $base = (string) config('services.superpart.base_url');
        $key = (string) config('services.superpart.api_key');
        $secret = (string) config('services.superpart.api_secret');
        if ($base === '' || $key === '' || $secret === '') {
            return false;
        }

        DB::table('superpart_outbox')->where('id', $row->id)->update([
            'status' => 'processing',
            'attempts' => (int) $row->attempts + 1,
        ]);

        $payload = is_string($row->payload) ? $row->payload : json_encode($row->payload, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $eventId = (string) $row->event_id;
        $bodyHash = hash('sha256', $payload);
        $signature = hash_hmac('sha256', $timestamp."\n".$eventId."\n".$bodyHash, $secret);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $key,
                    'X-Signature' => $signature,
                    'X-Timestamp' => $timestamp,
                    'X-Event-ID' => $eventId,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->withBody($payload, 'application/json')
                ->post(rtrim($base, '/').'/api/v1/webhooks/order-snapshot');

            if ($response->successful()) {
                DB::table('superpart_outbox')->where('id', $row->id)->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'last_error' => null,
                    'next_attempt_at' => null,
                ]);

                return true;
            }

            $attempts = (int) $row->attempts + 1;
            $delay = min(3600, (int) (5 * (2 ** max(0, $attempts - 1))));
            $dead = $attempts >= 12;
            DB::table('superpart_outbox')->where('id', $row->id)->update([
                'status' => $dead ? 'dead' : 'pending',
                'last_error' => Str::limit('HTTP '.$response->status().' '.Str::limit($response->body(), 120), 500),
                'next_attempt_at' => $dead ? null : now()->addSeconds($delay),
            ]);
            Log::warning('SuperpartOutboxDeliverer: HTTP error', [
                'event_id' => $eventId,
                'order_id' => $row->order_id,
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $attempts = (int) $row->attempts + 1;
            $delay = min(3600, (int) (5 * (2 ** max(0, $attempts - 1))));
            DB::table('superpart_outbox')->where('id', $row->id)->update([
                'status' => $attempts >= 12 ? 'dead' : 'pending',
                'last_error' => Str::limit($e->getMessage(), 500),
                'next_attempt_at' => $attempts >= 12 ? null : now()->addSeconds($delay),
            ]);
            Log::error('SuperpartOutboxDeliverer: exception', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
