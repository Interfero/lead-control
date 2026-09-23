<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Transactional outbox CRM→SuperPart (ТЗ FR-SYNC-02/03/04).
 */
class SuperpartOutboxWriter
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    /**
     * Инкремент sync_version + запись outbox в текущей/новой транзакции.
     *
     * @return string event_id
     */
    public function enqueueSnapshot(Order $order, ?int $forcePartnerUserId = null): string
    {
        return DB::transaction(function () use ($order, $forcePartnerUserId) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->order_id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['address.city', 'persons.phones', 'source']);

            $version = (int) ($locked->sync_version ?? 0) + 1;
            $locked->sync_version = $version;
            $locked->save();

            $eventId = (string) Str::uuid();
            $payload = $this->buildSnapshotPayload($locked, $version, $eventId, $forcePartnerUserId);

            DB::table('superpart_outbox')->insert([
                'event_id' => $eventId,
                'order_id' => (int) $locked->order_id,
                'order_version' => $version,
                'event_type' => 'order.snapshot.changed',
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => 'pending',
                'attempts' => 0,
                'next_attempt_at' => now(),
                'created_at' => now(),
            ]);

            $order->sync_version = $version;

            return $eventId;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshotPayload(Order $order, int $version, string $eventId, ?int $forcePartnerUserId = null): array
    {
        $partnerUserId = $forcePartnerUserId;
        if ($partnerUserId === null) {
            $partnerUserId = app(PartnerApiService::class)->resolvePartnerUserId($order);
        }

        $source = $order->source;
        $spAvailable = $source && (int) $source->available_for_superpart === 1 && (int) ($source->superpart_partner_id ?? 0) > 0;

        $person = $order->persons->first();
        $phone = $person?->phones->first();
        $base = max(0, $this->orderService->getNetAmount($order));
        $createdAt = $order->order_created_at;
        $isComputer = mb_strtolower(trim((string) $order->equipment_type)) === 'computer'
            && $createdAt
            && $createdAt->copy()->timezone('Europe/Moscow')->greaterThanOrEqualTo(
                \Illuminate\Support\Carbon::parse('2026-09-09 00:00:00', 'Europe/Moscow')
            );
        $rate = $isComputer ? 0.40 : 0.30;
        $rewardRule = $isComputer ? 'computer-40-20260909-v1' : 'default-30-cap2500-v1';
        $rewardAmount = (int) min(round($base * $rate), 2500);

        $shouldCharge = $order->order_closed_at
            && $order->order_status === 'completed'
            && $order->order_type !== 'warranty';

        $orderBlock = [
            'id' => (int) $order->order_id,
            'created_at' => $order->order_created_at
                ? $order->order_created_at->timezone('Europe/Moscow')->toIso8601String()
                : null,
            'closed_at' => $order->order_closed_at
                ? $order->order_closed_at->timezone('Europe/Moscow')->toIso8601String()
                : null,
            'status' => (string) $order->order_status,
            'source_id' => $order->source_id ? (int) $order->source_id : null,
            'source_available_for_superpart' => (bool) $spAvailable,
            'partner_user_id' => $partnerUserId ? (int) $partnerUserId : null,
            'equipment_type' => $order->equipment_type,
            'order_type' => $order->order_type,
            'order_core' => $order->order_core,
            'amount_paid' => (int) ($order->amount_paid ?? 0),
            'amount_parts' => (int) ($order->amount_comp ?? 0),
            'reward_amount' => $rewardAmount,
            'reward_rule' => $rewardRule,
            'should_charge' => (bool) $shouldCharge,
            'marketing_source_name' => $source?->source_name,
            'client_name' => $person?->person_name ?? 'Клиент',
            'client_phone' => $phone ? PhoneHelper::format($phone->phone_number) : '0000000000',
            'city_id' => $order->address?->city_id,
            'street' => trim((string) ($order->address?->street ?? '')) !== '' ? (string) $order->address->street : '—',
            'house' => trim((string) ($order->address?->house ?? '')) !== '' ? (string) $order->address->house : '—',
            'flat' => $order->address?->flat,
            'address_adds' => $order->address?->address_adds,
            'datetime_order' => ($order->datetime_order ?? now())->timezone('Europe/Moscow')->toIso8601String(),
            'order_adds' => $order->order_adds,
        ];

        $forChecksum = [
            'id' => $orderBlock['id'],
            'event_version' => $version,
            'created_at' => $orderBlock['created_at'],
            'closed_at' => $orderBlock['closed_at'],
            'status' => $orderBlock['status'],
            'source_id' => $orderBlock['source_id'],
            'source_available_for_superpart' => $orderBlock['source_available_for_superpart'],
            'partner_user_id' => $orderBlock['partner_user_id'],
            'equipment_type' => $orderBlock['equipment_type'],
            'order_type' => $orderBlock['order_type'],
            'amount_paid' => $orderBlock['amount_paid'],
            'amount_parts' => $orderBlock['amount_parts'],
            'reward_amount' => $orderBlock['reward_amount'],
            'reward_rule' => $orderBlock['reward_rule'],
        ];
        ksort($forChecksum);
        $checksum = hash('sha256', json_encode($forChecksum, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return [
            'event_id' => $eventId,
            'event_type' => 'order.snapshot.changed',
            'event_version' => $version,
            'occurred_at' => now('Europe/Moscow')->toIso8601String(),
            'order' => $orderBlock,
            'checksum' => $checksum,
        ];
    }
}
