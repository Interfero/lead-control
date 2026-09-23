<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Уведомления диспетчерам о прозвонах-событиях.
 * Событие = datetime_order (когда нужно позвонить). Без даты — fallback created_at + 24 ч.
 */
class CallbackNotificationService
{
    private const FALLBACK_DUE_HOURS = 24;

    /** @return list<int> */
    public function getDueCallbackOrderIds(User $user): array
    {
        return $this->dueCallbacksQuery($user)
            ->orderByDesc('order_id')
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function countDueCallbacks(User $user): int
    {
        return $this->dueCallbacksQuery($user)->count();
    }

    /**
     * @return Collection<int, Order>
     */
    public function getDueCallbacks(User $user, int $limit = 20): Collection
    {
        return $this->dueCallbacksQuery($user)
            ->with(['address.city', 'persons.phones'])
            ->orderByRaw('COALESCE(datetime_order, order_created_at) ASC')
            ->limit($limit)
            ->get();
    }

    private function dueCallbacksQuery(User $user): Builder
    {
        $now = now();

        $query = Order::query()
            ->where('order_status', 'callback')
            ->whereNull('order_closed_at')
            ->where(function (Builder $q) use ($now) {
                // Основной путь: время события (прозвона) уже наступило
                $q->where(function (Builder $inner) use ($now) {
                    $inner->whereNotNull('datetime_order')
                        ->where('datetime_order', '<=', $now);
                })->orWhere(function (Builder $inner) use ($now) {
                    // Заявки без даты события — через сутки после создания
                    $inner->whereNull('datetime_order')
                        ->where('order_created_at', '<=', $now->copy()->subHours(self::FALLBACK_DUE_HOURS));
                });
            });

        $cityIds = $user->cityIdsForOrdersFilter();
        if ($cityIds !== null) {
            if ($cityIds === []) {
                return $query->whereRaw('0 = 1');
            }
            $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds));
        }

        return $query;
    }
}
