<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MasterSettlementService
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function calculationGroupLabel(Order $order): string
    {
        return match ($order->order_core) {
            'core' => 'А',
            'non_core' => 'Б',
            default => 'П',
        };
    }

    /**
     * @return Builder<Order>
     */
    public function unsettledOrdersQuery(?array $cityIds, ?int $masterId = null): Builder
    {
        $query = Order::query()
            ->with(['address.city', 'persons', 'master'])
            ->where('order_status', 'completed')
            ->whereNotNull('order_closed_at')
            ->whereNotNull('master_id')
            ->whereNull('master_handed_over_at');

        if ($masterId) {
            $query->where('master_id', $masterId);
        }

        if ($cityIds !== null) {
            $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds));
        }

        return $query;
    }

    /**
     * @return Collection<int, array{master: User, city_name: string, amount_to_pay: int, orders_count: int}>
     */
    public function mastersForSettlement(User $viewer, ?int $cityFilter = null): Collection
    {
        $cityIds = $viewer->cityIdsForScope();
        if ($cityFilter) {
            if ($cityIds !== null && ! in_array($cityFilter, $cityIds, true)) {
                return collect();
            }
            $cityIds = [$cityFilter];
        }

        $orders = $this->unsettledOrdersQuery($cityIds)->get();

        return $orders
            ->groupBy('master_id')
            ->map(function (Collection $masterOrders) {
                /** @var Order $first */
                $first = $masterOrders->first();
                $master = $first->master ?? User::find($first->master_id);

                if (! $master) {
                    return null;
                }

                $amountToPay = $masterOrders->sum(
                    fn (Order $order) => $this->orderService->calculateAmountToPay($order)
                );

                $cityName = $first->address?->city?->city_name ?? '—';

                return [
                    'master' => $master,
                    'city_name' => $cityName,
                    'amount_to_pay' => (int) $amountToPay,
                    'orders_count' => $masterOrders->count(),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['master']->user_name)
            ->values();
    }

    /**
     * @return array{master: User, orders: Collection<int, array<string, mixed>>, total_to_pay: int}
     */
    public function masterDetail(User $viewer, int $masterId, ?string $sort = null, string $dir = 'desc'): array
    {
        $cityIds = $viewer->cityIdsForScope();

        $master = User::query()->findOrFail($masterId);

        $query = $this->unsettledOrdersQuery($cityIds, $masterId);

        if ($sort === 'order_closed_at') {
            $query->orderBy('order_closed_at', $dir);
        } else {
            $query->orderByDesc('order_closed_at');
        }

        $orders = $query
            ->get()
            ->map(function (Order $order) {
                $person = $order->persons->first();

                return [
                    'order' => $order,
                    'order_id' => $order->order_id,
                    'closed_at' => $order->order_closed_at,
                    'address' => $this->formatOrderAddress($order),
                    'client_name' => $person?->person_name ?? '—',
                    'amount_paid' => (int) $order->amount_paid,
                    'calculation_group' => $this->calculationGroupLabel($order),
                    'amount_to_pay' => $this->orderService->calculateAmountToPay($order),
                ];
            });

        return [
            'master' => $master,
            'orders' => $orders,
            'total_to_pay' => (int) $orders->sum('amount_to_pay'),
        ];
    }

    /**
     * @param  array<int>  $orderIds
     */
    public function markHandedOver(User $viewer, array $orderIds): int
    {
        $cityIds = $viewer->cityIdsForScope();
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));

        if ($orderIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($viewer, $cityIds, $orderIds) {
            $query = Order::query()
                ->whereIn('order_id', $orderIds)
                ->whereNull('master_handed_over_at')
                ->where('order_status', 'completed')
                ->whereNotNull('order_closed_at');

            if ($cityIds !== null) {
                $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds));
            }

            $now = now();
            $userId = $viewer->user_id;

            return $query->update([
                'master_handed_over_at' => $now,
                'master_handed_over_by' => $userId,
            ]);
        });
    }

    /**
     * @param  array<int>  $masterIds
     */
    public function markHandedOverForMasters(User $viewer, array $masterIds): int
    {
        $cityIds = $viewer->cityIdsForScope();
        $masterIds = array_values(array_unique(array_map('intval', $masterIds)));

        if ($masterIds === []) {
            return 0;
        }

        $orderIds = $this->unsettledOrdersQuery($cityIds)
            ->whereIn('master_id', $masterIds)
            ->pluck('order_id')
            ->all();

        return $this->markHandedOver($viewer, $orderIds);
    }

    private function formatOrderAddress(Order $order): string
    {
        $address = $order->address;
        if (! $address) {
            return '—';
        }

        $city = $address->city?->city_name;
        $line = $address->full_address;

        return trim(($city ? "г. {$city}, " : '').$line) ?: '—';
    }
}
