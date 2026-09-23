<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmOrderSyncService
{
    public function __construct(
        private GmUserSyncService $gmUserSyncService
    ) {}

    public function sync(Order $order): array
    {
        $baseUrl = (string) config('services.gm_api.base_url');
        $bearerToken = (string) config('services.gm_api.bearer_token');
        $path = (string) config('services.gm_api.order_upsert_path', '/api/integrations/orders/upsert');

        if ($baseUrl === '' || $bearerToken === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'GM API is not configured.',
            ];
        }

        $order->loadMissing([
            'address.city',
            'master',
            'master.roles',
            'master.cities',
            'creator',
            'creator.roles',
            'creator.cities',
            'persons.phones',
            'documents',
        ]);

        $userSync = $this->syncRelatedUsers($order);
        if (($userSync['ok'] ?? false) === false) {
            return [
                'ok' => false,
                'reason' => 'GM user sync failed before order sync.',
                'user_sync' => $userSync,
            ];
        }

        $payload = $this->buildPayload($order);

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->withToken($bearerToken)
                ->timeout((int) config('services.gm_api.timeout', 10))
                ->withOptions([
                    'verify' => (bool) config('services.gm_api.verify_tls', true),
                ])
                ->post($path, $payload);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'reason' => $response->json('error') ?: $response->body(),
                    'payload' => $payload,
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'payload' => $payload,
                'user_sync' => $userSync,
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'reason' => $e->getMessage(),
                'payload' => $payload,
                'user_sync' => $userSync,
            ];
        }
    }

    public function logResult(Order $order, array $result): void
    {
        $context = [
            'order_id' => $order->order_id,
            'master_id' => $order->master_id,
            'gm_sync' => $result,
        ];

        if (($result['ok'] ?? false) === true) {
            Log::info('GM order sync succeeded.', $context);

            return;
        }

        Log::warning('GM order sync failed.', $context);
    }

    public function deleteOrderDocument(int $documentId): array
    {
        $baseUrl = (string) config('services.gm_api.base_url');
        $bearerToken = (string) config('services.gm_api.bearer_token');

        if ($baseUrl === '' || $bearerToken === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'GM API is not configured.',
            ];
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->withToken($bearerToken)
                ->timeout((int) config('services.gm_api.timeout', 10))
                ->withOptions([
                    'verify' => (bool) config('services.gm_api.verify_tls', true),
                ])
                ->delete('/api/integrations/order-documents/'.rawurlencode((string) $documentId));

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'reason' => $response->json('error') ?: $response->body(),
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'reason' => $e->getMessage(),
            ];
        }
    }

    private function buildPayload(Order $order): array
    {
        $address = $order->address?->full_address
            ?? implode(', ', array_filter([
                $order->address?->city?->city_name,
                $order->address?->street,
                $order->address?->house,
                $order->address?->flat,
            ]));
        $clientPhone = $this->extractClientPhone($order);
        $scheduledAt = $order->datetime_order ?? $order->order_created_at ?? now();

        return [
            'externalSource' => 'LC',
            'externalId' => (string) $order->order_id,
            'number' => (string) $order->order_id,
            'city' => $order->address?->city?->city_name ?: 'Unknown',
            'address' => $address ?: 'Unknown address',
            'apartment' => $order->address?->flat,
            'addressComment' => $order->shift_adds,
            'orderComment' => $this->buildOrderComment($order, $clientPhone),
            'clientName' => $order->persons->first()?->person_name ?: 'Unknown client',
            'clientAge' => 0,
            'scheduledAt' => $scheduledAt->toIso8601String(),
            'kind' => $this->mapKind($order->order_type),
            'profile' => $this->mapProfile($order->order_core),
            'status' => $this->mapStatus($order->order_status),
            'hasDocuments' => $order->documents->isNotEmpty(),
            'reviewComment' => $order->city_adds,
            'assignedMaster' => $order->master ? [
                'email' => $order->master->email,
                'externalId' => (string) $order->master->user_id,
                'externalSource' => 'GM_MESSENGER',
            ] : null,
            'assignedManager' => $order->creator && $order->creator->email ? [
                'email' => $order->creator->email,
            ] : null,
        ];
    }

    private function buildOrderComment(Order $order, ?string $clientPhone): ?string
    {
        $lines = array_values(array_filter([
            $order->order_adds ? 'LC notes: '.$order->order_adds : null,
            $order->equipment_type ? 'Equipment: '.$order->equipment_type : null,
            $order->shift_adds ? 'Previous contacts: '.$order->shift_adds : null,
            $order->city_adds ? 'City comment: '.$order->city_adds : null,
            $clientPhone ? 'Client phone: '.$clientPhone : null,
        ]));

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function extractClientPhone(Order $order): ?string
    {
        $rawPhone = $order->persons->first()?->phones->first()?->phone_number;
        $digits = preg_replace('/\D+/', '', (string) $rawPhone);

        if (! $digits) {
            return null;
        }

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)) {
            return '+7'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    private function mapKind(?string $orderType): string
    {
        return in_array($orderType, ['repeat', 'warranty'], true) ? 'REPEAT' : 'FIRST_TIME';
    }

    private function mapProfile(?string $orderCore): string
    {
        return $orderCore === 'core' ? 'PROFILE' : 'NON_PROFILE';
    }

    private function mapStatus(?string $orderStatus): string
    {
        return match ($orderStatus) {
            'on_way', 'in_progress_sd' => 'ON_WAY',
            'review' => 'REVIEW',
            'completed', 'cancelled_cc', 'cancelled_city', 'rejected' => 'CLOSED',
            default => 'ACTIVE',
        };
    }

    private function syncRelatedUsers(Order $order): array
    {
        $users = new \Illuminate\Database\Eloquent\Collection();

        if ($order->master) {
            $users->push($order->master);
        }

        if ($order->creator && $order->creator->email) {
            $users->push($order->creator);
        }

        if ($users->isEmpty()) {
            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'No related users to sync.',
            ];
        }

        return $this->gmUserSyncService->syncMany($users->unique('user_id')->values());
    }
}
