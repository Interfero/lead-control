<?php

namespace App\Services;

use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DispatcherDashboardService
{
    /** Скользящее окно: только закрытые за последние 7 суток */
    public function windowStart(): Carbon
    {
        return now()->subDays(7)->startOfSecond();
    }

    public function getCityIdsForUser(User $user): ?array
    {
        if ($user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher']) || $user->access_all_cities) {
            return null;
        }

        return $user->cities->pluck('city_id')->all();
    }

    public function baseClosedQuery(User $user, ?int $filterCityId = null, ?int $filterMasterId = null, mixed $filterSourceId = null, mixed $filterSourceFormat = null)
    {
        $query = Order::query()
            ->whereNotNull('order_closed_at')
            ->where('order_closed_at', '>=', $this->windowStart())
            ->with(['address.city', 'master', 'creator', 'closedBy', 'persons', 'source'])
            ->filterBySource($filterSourceId, $filterSourceFormat);

        $cityIds = $this->getCityIdsForUser($user);
        if ($cityIds !== null) {
            $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds));
        }

        if ($filterCityId) {
            $query->whereHas('address', fn ($q) => $q->where('city_id', $filterCityId));
        }

        if ($filterMasterId) {
            $query->where('master_id', $filterMasterId);
        }

        return $query;
    }

    public function getStats(User $user, ?int $filterCityId = null, ?int $filterMasterId = null, mixed $filterSourceId = null, mixed $filterSourceFormat = null): array
    {
        $closed = $this->baseClosedQuery($user, $filterCityId, $filterMasterId, $filterSourceId, $filterSourceFormat)->get();
        $netSum = $closed->sum(fn (Order $o) => $o->amount_paid - $o->amount_comp);
        $count = $closed->count();

        $acceptedQuery = Order::query()
            ->where('order_created_at', '>=', $this->windowStart());

        $cityIds = $this->getCityIdsForUser($user);
        if ($cityIds !== null) {
            $acceptedQuery->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds));
        }
        if ($filterCityId) {
            $acceptedQuery->whereHas('address', fn ($q) => $q->where('city_id', $filterCityId));
        }
        if ($filterMasterId) {
            $acceptedQuery->where('master_id', $filterMasterId);
        }
        $acceptedQuery->filterBySource($filterSourceId, $filterSourceFormat);

        $acceptedCount = $acceptedQuery->count();

        return [
            'window_from' => $this->windowStart()->format('d.m.Y H:i'),
            'window_to' => now()->format('d.m.Y H:i'),
            'closed_count' => $count,
            'closed_net_sum' => $netSum,
            'closed_avg_net' => $count > 0 ? (int) round($netSum / $count) : 0,
            'accepted_count' => $acceptedCount,
        ];
    }

    public function getClosedOrders(User $user, ?int $filterCityId = null, ?int $filterMasterId = null, mixed $filterSourceId = null, mixed $filterSourceFormat = null): Collection
    {
        return $this->baseClosedQuery($user, $filterCityId, $filterMasterId, $filterSourceId, $filterSourceFormat)
            ->orderByDesc('order_closed_at')
            ->get();
    }

    public function getFilterCities(User $user): Collection
    {
        if ($this->getCityIdsForUser($user) === null) {
            return City::where('is_active', true)->orderBy('city_name')->get();
        }

        return $user->cities()->where('is_active', true)->orderBy('city_name')->get();
    }

    public function getFilterMasters(User $user, ?int $filterCityId = null): Collection
    {
        $q = User::query()
            ->whereHas('roles', fn ($r) => $r->where('role_code', 'master'))
            ->where('is_active', true)
            ->orderBy('user_name');

        if ($filterCityId) {
            $q->whereHas('cities', fn ($c) => $c->where('cities.city_id', $filterCityId));
        } elseif ($this->getCityIdsForUser($user) !== null) {
            $q->whereHas('cities', fn ($c) => $c->whereIn('cities.city_id', $this->getCityIdsForUser($user)));
        }

        return $q->get();
    }

    /**
     * Активные заявки для главного экрана диспетчера.
     */
    public function getActiveOrdersForCallCenter(): Collection
    {
        return Order::query()
            ->with(['address.city', 'master', 'source', 'persons.phones'])
            ->whereIn('order_status', Order::dispatcherActiveStatusCodes())
            ->where(function ($q) {
                $q->whereNull('order_core')
                    ->orWhereIn('order_core', ['core', 'other']);
            })
            ->orderByDispatcherActive()
            ->get();
    }

    /** Непрофильные активные заявки — отдельный блок вне основного списка. */
    public function getNonCoreActiveOrdersForCallCenter(): Collection
    {
        return Order::query()
            ->with(['address.city', 'master', 'source', 'persons.phones'])
            ->whereIn('order_status', Order::dispatcherActiveStatusCodes())
            ->where('order_core', 'non_core')
            ->orderByDispatcherActive()
            ->get();
    }
}
