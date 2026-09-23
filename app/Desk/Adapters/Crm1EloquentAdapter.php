<?php

namespace App\Desk\Adapters;

use App\Desk\Contracts\CrmAdapter;
use App\Models\City;
use App\Models\CrmConnection;
use App\Models\Order;
use App\Support\OrderLeadSource;
use App\Support\OrderAddressReveal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * CRM1 = Lead Control: прямое чтение/запись через Eloquent (та же БД).
 */
class Crm1EloquentAdapter implements CrmAdapter
{
    public function __construct(
        protected CrmConnection $connection
    ) {}

    public function authenticate(): void
    {
        // Локальная БД — сессия не нужна.
    }

    public function fetchOrders(array $filters = []): array
    {
        $closed = $this->closedRawStatuses();

        $query = Order::query()
            ->with([
                'address.city',
                'master',
                'persons.phones',
                'documents',
                'source',
            ]);

        // Как Desk API: активные всегда + недавно закрытые
        $query->where(function ($q) use ($closed) {
            $q->whereNotIn('order_status', $closed)
                ->orWhere('order_closed_at', '>=', now()->subDays(14));
        });

        if (! empty($filters['full'])) {
            // без доп. ограничения
        } elseif (! empty($filters['updated_after'])) {
            $after = Carbon::parse($filters['updated_after']);
            $query->where(function ($q) use ($after, $closed) {
                $q->whereNotIn('order_status', $closed)
                    ->orWhere('order_closed_at', '>=', $after)
                    ->orWhere('order_created_at', '>=', $after)
                    ->orWhere('datetime_order', '>=', $after);
            });
        } else {
            $query->where(function ($q) {
                $q->where('order_created_at', '>=', now()->subDays(30))
                    ->orWhere('order_closed_at', '>=', now()->subDays(14));
            });
        }

        return $query
            ->orderByDesc('order_created_at')
            ->limit(5000)
            ->get()
            ->map(fn (Order $order) => $this->mapOrder($order))
            ->all();
    }

    public function fetchOrder(string $externalId): ?array
    {
        $order = Order::query()
            ->with(['address.city', 'master', 'persons.phones', 'documents', 'source'])
            ->find((int) $externalId);

        return $order ? $this->mapOrder($order) : null;
    }

    public function updateOrder(string $externalId, array $data): void
    {
        $order = Order::query()->find((int) $externalId);
        if (! $order) {
            throw new RuntimeException("Заказ #{$externalId} не найден в CRM1");
        }

        if ($order->isClosedStatus()) {
            throw new RuntimeException('Заказ уже закрыт в CRM1');
        }

        $fill = [];

        if (array_key_exists('raw_status', $data) && $data['raw_status']) {
            $fill['order_status'] = $data['raw_status'];
        }

        if (array_key_exists('paid_amount', $data) && $data['paid_amount'] !== null) {
            $fill['amount_paid'] = (int) $data['paid_amount'];
        }

        if (array_key_exists('parts_amount', $data) && $data['parts_amount'] !== null) {
            $fill['amount_comp'] = (int) $data['parts_amount'];
        }

        if (array_key_exists('comment', $data) && filled($data['comment'])) {
            $existing = trim((string) ($order->city_adds ?? ''));
            $stamp = now()->format('d.m.Y H:i');
            $userName = Auth::user()?->user_name ?? 'desk';
            $line = "[{$stamp} {$userName}] ".$data['comment'];
            $fill['city_adds'] = $existing === '' ? $line : $existing."\n".$line;
        }

        if ($fill === []) {
            return;
        }

        $order->fill($fill);
        $order->save();
    }

    public function closeOrder(string $externalId, array $documents = []): void
    {
        $order = Order::query()->find((int) $externalId);
        if (! $order) {
            throw new RuntimeException("Заказ #{$externalId} не найден в CRM1");
        }

        if ($order->isClosedStatus()) {
            return;
        }

        $order->order_status = 'completed';
        $order->order_closed_at = now();
        $order->order_closed_by = Auth::id();
        $order->save();
    }

    public function getStatuses(): array
    {
        return Order::getStatusLabels();
    }

    public function getCities(): array
    {
        return City::query()
            ->where('is_active', true)
            ->orderBy('city_name')
            ->pluck('city_name', 'city_id')
            ->all();
    }

    /** @return list<string> */
    protected function closedRawStatuses(): array
    {
        return ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapOrder(Order $order): array
    {
        $city = $order->address?->city;
        $person = $order->persons->first();
        $phone = $person?->phones->first()?->phone_number;

        $addressParts = [];
        if ($city?->city_name) {
            $addressParts[] = 'г. '.$city->city_name;
        }
        if ($order->address) {
            $streetOnly = OrderAddressReveal::streetLine($order->address);
            if ($streetOnly !== '') {
                $addressParts[] = $streetOnly;
            }
        }

        $docs = $order->documents->map(fn ($d) => [
            'id' => $d->document_id,
            'name' => $d->file_name ?? ('doc-'.$d->document_id),
            'url' => null,
        ])->values()->all();

        $payload = [
            'external_id' => (string) $order->order_id,
            'source' => OrderLeadSource::LC,
            'rk' => $order->source?->deskRkName() ?: null,
            'rk_url' => $order->source?->deskRkUrl(),
            'marketing_source' => $order->source?->deskRkName() ?: null,
            'city_id' => $order->address?->city_id,
            'city_name' => $city?->city_name,
            'raw_status' => (string) $order->order_status,
            'order_status' => (string) $order->order_status,
            'client_name' => $person?->person_name,
            'phone' => $phone,
            'address' => implode(', ', array_filter($addressParts)),
            'address_office' => OrderAddressReveal::officeTextFromAddress($order->address),
            'description' => $order->order_adds,
            'master_name' => $order->master?->user_name,
            'total_amount' => $order->amount_paid !== null
                ? max(0, (int) $order->amount_paid - (int) ($order->amount_comp ?? 0))
                : null,
            'paid_amount' => $order->amount_paid,
            'parts_amount' => $order->amount_comp,
            'created_at_local' => optional($order->order_created_at)?->toDateTimeString(),
            'call_at_local' => optional($order->datetime_order)?->toDateTimeString(),
            'timezone' => $city?->city_timezone,
            'updated_at_local' => optional($order->order_closed_at ?? $order->order_created_at)?->toDateTimeString(),
            'order_type' => match ($order->order_type) {
                'repeat' => 'repeat',
                'warranty' => 'warranty',
                default => 'first',
            },
            'comments' => collect([
                $order->order_adds ? 'Проблема: '.$order->order_adds : null,
                $order->shift_adds ? 'Переносы: '.$order->shift_adds : null,
                $order->city_adds ? 'Филиал: '.$order->city_adds : null,
            ])->filter()->implode("\n"),
            'documents' => $docs,
            'priority' => $this->priorityFor($order),
            'row_highlight' => null,
        ];

        $payload = array_merge($payload, app(\App\Services\OrderService::class)->deskSerializationExtras($order));

        $payload['hash'] = $this->hashPayload($payload);

        return $payload;
    }

    protected function priorityFor(Order $order): int
    {
        return match ($order->order_status) {
            'callback', 'not_processed' => 10,
            'pending', 'on_way' => 20,
            'in_progress', 'in_progress_sd' => 30,
            'review' => 40,
            default => 50,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hashPayload(array $payload): string
    {
        $slice = [
            $payload['raw_status'],
            $payload['paid_amount'],
            $payload['parts_amount'],
            $payload['master_name'],
            $payload['comments'],
            $payload['call_at_local'],
            $payload['order_core'] ?? null,
            $payload['is_noncore'] ?? false,
            $payload['is_long_trip'] ?? false,
            $payload['is_satellite'] ?? false,
            $payload['is_partner_order'] ?? false,
            count($payload['documents'] ?? []),
        ];

        return hash('sha256', json_encode($slice, JSON_UNESCAPED_UNICODE));
    }
}
