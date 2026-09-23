<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Order;
use App\Models\Source;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PartnerApiService
{
    public function isConfigured(): bool
    {
        return config('services.superpart.base_url') !== ''
            && config('services.superpart.api_key') !== ''
            && config('services.superpart.api_secret') !== '';
    }

    /**
     * Партнёр SuperPart: только если текущая РК в каталоге SuperPart.
     * Sticky partner_user_id не держит заявку в SP после смены на «Листовку».
     */
    public function resolvePartnerUserId(Order $order): ?int
    {
        $order->loadMissing('source');
        $source = $order->source;

        if ($source && ! $source->available_for_superpart) {
            return null;
        }

        if ($source && $source->available_for_superpart && $source->superpart_partner_id) {
            return (int) $source->superpart_partner_id;
        }

        // Sticky только пока РК всё ещё SuperPart (или источник ещё не подгрузился).
        if ($order->partner_user_id && (! $source || $source->available_for_superpart)) {
            return (int) $order->partner_user_id;
        }

        return null;
    }

    /**
     * Проставить / снять partner_user_id по текущему источнику CRM.
     */
    public function syncOrderPartnerFromSource(Order $order): bool
    {
        $order->loadMissing('source');
        $source = $order->source;

        if (! $source || ! $source->available_for_superpart) {
            if ($order->partner_user_id !== null) {
                $order->partner_user_id = null;
                $order->save();

                return true;
            }

            return false;
        }

        $partnerId = $source->superpart_partner_id ? (int) $source->superpart_partner_id : null;

        // Общий SP-источник без явного партнёра — не затираем уже проставленный sticky.
        if ($partnerId === null) {
            return false;
        }

        if ((int) ($order->partner_user_id ?? 0) === $partnerId) {
            return false;
        }

        $order->partner_user_id = $partnerId;
        $order->save();

        return true;
    }

    /**
     * Единая точка CRM→SP после создания/догона заявки.
     * Синхронизирует partner_user_id и сразу шлёт partner-order-created (dispatchSync).
     *
     * @param  int|null  $forcePartnerUserId  Для leave-SP: обновить уже существующую заявку в SP со старым партнёром.
     */
    public function pushCreatedToSuperpart(Order $order, ?int $forcePartnerUserId = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            if ($forcePartnerUserId === null) {
                $this->syncOrderPartnerFromSource($order);
            }

            $order->refresh()->loadMissing(['address.city', 'persons.phones', 'source']);

            $partnerUserId = $forcePartnerUserId ?? $this->resolvePartnerUserId($order);
            if (! $partnerUserId && $forcePartnerUserId === null) {
                return false;
            }

            // FR-SYNC-02: outbox + немедленная доставка (SLA); cron догонит pending.
            $eventId = $this->enqueueSnapshotForOrder($order, $forcePartnerUserId);
            if ($eventId === null) {
                return false;
            }

            return app(SuperpartOutboxDeliverer::class)->deliverEventId($eventId);
        } catch (\Throwable $e) {
            // Заказ в CRM уже сохранён — сбой синка в SuperPart не должен отдавать Server Error диспетчеру.
            Log::error('PartnerApiService: pushCreatedToSuperpart failed', [
                'order_id' => $order->order_id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Только запись outbox в текущей DB-транзакции (доставка — после commit).
     */
    public function enqueueSnapshotForOrder(Order $order, ?int $forcePartnerUserId = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($forcePartnerUserId === null) {
            $this->syncOrderPartnerFromSource($order);
        }

        $order->refresh()->loadMissing(['address.city', 'persons.phones', 'source']);

        $partnerUserId = $forcePartnerUserId ?? $this->resolvePartnerUserId($order);
        if (! $partnerUserId && $forcePartnerUserId === null) {
            return null;
        }

        return app(SuperpartOutboxWriter::class)->enqueueSnapshot($order, $forcePartnerUserId);
    }

    /**
     * Уведомление SuperPart: источник создан/обновлён в CRM.
     */
    public function notifyReferenceSourceUpsert(Source $source): bool
    {
        $source->loadMissing('city');

        return $this->postJson('/api/v1/webhooks/reference-source-upsert', [
            'source_id' => $source->source_id,
            'source_name' => $source->source_name,
            'city_id' => $source->city_id,
            'city_name' => $source->city?->city_name,
            'superpart_partner_id' => $source->superpart_partner_id,
            'superpart_local_source_id' => $source->superpart_local_source_id,
            'source_kind' => $source->source_kind,
            'source_url' => $source->use_source_url ? $source->source_url : null,
            'use_source_url' => $source->use_source_url,
            'is_active' => $source->is_active,
            'available_for_superpart' => $source->available_for_superpart,
        ]);
    }

    /**
     * Уведомление SuperPart: источник удалён или снят с каталога в CRM.
     */
    public function notifyReferenceSourceDelete(Source $source): bool
    {
        return $this->notifyReferenceSourceDeletePayload([
            'source_id' => $source->source_id,
            'source_name' => $source->source_name,
            'superpart_local_source_id' => $source->superpart_local_source_id,
            'superpart_partner_id' => $source->superpart_partner_id,
            'is_active' => false,
            'available_for_superpart' => false,
            'deleted' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyReferenceSourceDeletePayload(array $payload): bool
    {
        return $this->postJson('/api/v1/webhooks/reference-source-upsert', $payload);
    }

    /**
     * Синхронизация одного заказа: партнёр → создание/обновление в SuperPart → начисление при проведении.
     *
     * @return array{partner_user_id: int|null, created: bool, charged: bool, errors: list<string>}
     */
    public function syncOrderToSuperpart(Order $order, bool $chargeIfClosed = true): array
    {
        $result = [
            'partner_user_id' => null,
            'created' => false,
            'charged' => false,
            'errors' => [],
        ];

        if (! $this->isConfigured()) {
            $result['errors'][] = 'SuperPart не настроен (SUPERPART_BASE_URL / API ключи)';

            return $result;
        }

        $this->syncOrderPartnerFromSource($order);
        $order->refresh()->loadMissing(['address.city', 'persons.phones', 'source']);

        $partnerUserId = $this->resolvePartnerUserId($order);
        $result['partner_user_id'] = $partnerUserId;

        if (! $partnerUserId) {
            $result['errors'][] = 'Нет partner_user_id и у источника не задан superpart_partner_id';

            return $result;
        }

        if (! $order->address) {
            $result['errors'][] = 'У заказа нет адреса';

            return $result;
        }

        $result['created'] = $this->notifyPartnerOrderCreated($order);
        if (! $result['created']) {
            $result['errors'][] = 'order-snapshot/outbox: ошибка доставки';
        }

        // Charge входит в snapshot (should_charge); отдельный order-completed оставляем как fallback.
        if ($chargeIfClosed
            && $order->order_closed_at
            && $order->order_status === 'completed'
            && ! $result['created']) {
            $result['charged'] = $this->notifyOrderCompletedLegacy($order);
            if (! $result['charged']) {
                $result['errors'][] = 'order-completed: ошибка HTTP';
            }
        } elseif ($chargeIfClosed
            && $order->order_closed_at
            && $order->order_status === 'completed') {
            $result['charged'] = true;
        }

        return $result;
    }

    /**
     * Уведомление SuperPart: заказ проведён в CRM, начисление партнёра.
     * Основной путь — snapshot через outbox; legacy endpoint — запасной.
     */
    public function notifyOrderCompleted(Order $order): bool
    {
        try {
            $cancelled = in_array($order->order_status, ['cancelled_cc', 'cancelled_city'], true);
            if ($cancelled) {
                return false;
            }

            $order->refresh()->loadMissing(['address.city', 'persons.phones', 'source']);
            $eventId = app(SuperpartOutboxWriter::class)->enqueueSnapshot($order);

            return app(SuperpartOutboxDeliverer::class)->deliverEventId($eventId);
        } catch (\Throwable $e) {
            Log::error('PartnerApiService: notifyOrderCompleted failed', [
                'order_id' => $order->order_id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Legacy order-completed webhook (до полного перехода на snapshot).
     */
    public function notifyOrderCompletedLegacy(Order $order): bool
    {
        $partnerUserId = $this->resolvePartnerUserId($order);

        $cancelled = in_array($order->order_status, ['cancelled_cc', 'cancelled_city'], true);
        if ($cancelled) {
            return false;
        }

        $orderService = app(OrderService::class);
        $base = max(0, $orderService->getNetAmount($order));
        $createdAt = $order->order_created_at;
        $isComputer = mb_strtolower(trim((string) $order->equipment_type)) === 'computer'
            && $createdAt
            && $createdAt->copy()->timezone('Europe/Moscow')->greaterThanOrEqualTo(
                \Illuminate\Support\Carbon::parse('2026-09-09 00:00:00', 'Europe/Moscow')
            );
        $chargeAmount = round($base * ($isComputer ? 0.40 : 0.30), 2);
        $chargeAmount = min($chargeAmount, 2500);

        $payload = [
            'order_id' => $order->order_id,
            'source_id' => $order->source_id,
            'order_status' => $order->order_status,
            'order_core' => $order->order_core,
            'equipment_type' => $order->equipment_type,
            'amount_paid' => $order->amount_paid,
            'amount_comp' => $order->amount_comp,
            'partner_reward_base' => $base,
            'charge_amount' => $chargeAmount,
            'completed_at' => $order->order_closed_at?->toIso8601String(),
        ];

        if ($partnerUserId) {
            $payload['partner_user_id'] = $partnerUserId;
        }

        return $this->postJson('/api/v1/webhooks/order-completed', $payload);
    }

    /**
     * Уведомление SuperPart: заказ из CRM (создание или обновление источника).
     *
     * @param  int|null  $forcePartnerUserId  Для смены РК «ушли из SP» — шлём со старым partner_user_id,
     *                                        чтобы SuperPart обновил reference_source у уже существующей заявки.
     */
    public function notifyPartnerOrderCreated(Order $order, ?int $forcePartnerUserId = null): bool
    {
        try {
            $order->refresh()->loadMissing(['address.city', 'persons.phones', 'source']);
            $partnerUserId = $forcePartnerUserId ?? $this->resolvePartnerUserId($order);
            if (! $partnerUserId && $forcePartnerUserId === null) {
                return false;
            }

            $eventId = app(SuperpartOutboxWriter::class)->enqueueSnapshot($order, $forcePartnerUserId);

            return app(SuperpartOutboxDeliverer::class)->deliverEventId($eventId);
        } catch (\Throwable $e) {
            Log::error('PartnerApiService: notifyPartnerOrderCreated failed', [
                'order_id' => $order->order_id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Legacy partner-order-created (без outbox) — для аварийного догона.
     */
    public function notifyPartnerOrderCreatedLegacy(Order $order, ?int $forcePartnerUserId = null): bool
    {
        $partnerUserId = $forcePartnerUserId ?? $this->resolvePartnerUserId($order);
        if (! $partnerUserId) {
            return false;
        }

        $order->loadMissing(['address.city', 'persons.phones', 'source']);

        $person = $order->persons->first();
        $phone = $person?->phones->first();

        return $this->postJson('/api/v1/webhooks/partner-order-created', [
            'order_id' => $order->order_id,
            'partner_user_id' => $partnerUserId,
            'source_id' => $order->source_id,
            'marketing_source_name' => $order->source?->source_name,
            'client_name' => $person?->person_name ?? 'Клиент',
            'client_phone' => $phone ? PhoneHelper::format($phone->phone_number) : '0000000000',
            'city_id' => $order->address->city_id,
            'street' => $this->nonEmptyAddressPart($order->address->street),
            'house' => $this->nonEmptyAddressPart($order->address->house),
            'flat' => $order->address->flat,
            'address_adds' => $order->address->address_adds,
            'order_core' => $order->order_core,
            'equipment_type' => $order->equipment_type,
            'datetime_order' => ($order->datetime_order ?? now())->toIso8601String(),
            'order_adds' => $order->order_adds,
            'order_type' => $order->order_type,
            'order_status' => $order->order_status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postJson(string $path, array $payload): bool
    {
        $base = config('services.superpart.base_url');
        $key = config('services.superpart.api_key');
        $secret = config('services.superpart.api_secret');

        if ($base === '' || $key === '' || $secret === '') {
            Log::warning('PartnerApiService: SuperPart base_url / api_key / api_secret не заданы, webhook пропущен', [
                'path' => $path,
            ]);

            return false;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $secret);
        $bases = array_values(array_unique(array_filter([
            rtrim((string) $base, '/'),
            rtrim((string) config('services.superpart.fallback_url', ''), '/'),
        ])));

        $lastError = null;
        foreach ($bases as $root) {
            $url = $root.$path;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $response = Http::timeout(30)
                        ->connectTimeout(10)
                        ->withHeaders([
                            'X-API-Key' => $key,
                            'X-Signature' => $signature,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->withBody($body, 'application/json')
                        ->post($url);

                    if ($response->status() === 429 && $attempt < 2) {
                        $wait = min(max((int) $response->header('Retry-After', 2), 1), 8);
                        sleep($wait);

                        continue;
                    }

                    if (! $response->successful()) {
                        $lastError = 'HTTP '.$response->status();
                        Log::error('PartnerApiService: SuperPart вернул ошибку', [
                            'path' => $path,
                            'status' => $response->status(),
                            'order_id' => $payload['order_id'] ?? $payload['id'] ?? null,
                            'response_snippet' => Str::limit((string) $response->body(), 200),
                        ]);

                        break;
                    }

                    return true;
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $lastError = $e->getMessage();
                    Log::warning('PartnerApiService: SuperPart недоступен, пробую запасной URL', [
                        'path' => $path,
                        'host' => parse_url($url, PHP_URL_HOST),
                    ]);

                    break;
                } catch (\Throwable $e) {
                    Log::error('PartnerApiService: сбой HTTP к SuperPart', [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);

                    return false;
                }
            }
        }

        if ($lastError !== null) {
            Log::error('PartnerApiService: сбой HTTP к SuperPart', [
                'path' => $path,
                'error' => $lastError,
            ]);
        }

        return false;
    }

    private function nonEmptyAddressPart(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : '—';
    }
}
