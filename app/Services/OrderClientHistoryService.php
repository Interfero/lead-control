<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderClientHistoryService
{
    private const CREATOR_SHORT_LABELS = [
        'call_center' => 'КЦ',
        'senior_dispatcher' => 'СД',
        'developer' => 'DEV',
        'branch_head' => 'РФ',
        'senior_manager' => 'СМ',
        'regional_director' => 'РД',
        'general_director' => 'ГД',
        'tech_director' => 'ТД',
        'master' => 'М',
    ];

    /**
     * @param  array<int, int>  $personIds
     * @return Collection<int, Order>
     */
    public function forPersonIds(array $personIds, User $user, ?int $highlightOrderId = null): Collection
    {
        if ($personIds === []) {
            return collect();
        }

        $query = Order::query()
            ->whereHas('persons', fn (Builder $q) => $q->whereIn('persons.person_id', $personIds))
            ->with([
                'master',
                'creator.roles',
                'source',
                'address.city',
            ])
            ->withExists(['complaints as has_complaint'])
            ->orderByDesc('order_id');

        if (! $user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'general_director'])) {
            $cityIds = $user->cityIdsForOrdersFilter() ?? [];
            if ($cityIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('address', fn (Builder $q) => $q->whereIn('city_id', $cityIds));
            }
        }

        $orders = $query->get();

        if ($highlightOrderId) {
            $orders = $orders->sortByDesc(fn (Order $order) => $order->order_id === $highlightOrderId ? 1 : 0)->values();
        }

        return $orders;
    }

    public function creatorShortLabel(?User $user): string
    {
        if (! $user) {
            return '—';
        }

        foreach (self::CREATOR_SHORT_LABELS as $roleCode => $label) {
            if ($user->hasRole($roleCode)) {
                return $label;
            }
        }

        $role = $user->roles->first();

        return $role?->role_name ? mb_substr($role->role_name, 0, 12) : ($user->user_name ?: '—');
    }

    public function formatAcceptedAt(Order $order): string
    {
        if (! $order->order_created_at) {
            return '—';
        }

        $timezone = $order->address?->city?->city_timezone ?? config('app.timezone', 'Europe/Moscow');
        $created = $order->order_created_at->copy()->timezone($timezone)->format('d.m.y H:i');
        $meeting = $order->datetime_order
            ? $order->datetime_order->copy()->timezone($timezone)->format('H:i')
            : null;

        return $meeting ? "{$created} ({$meeting})" : $created;
    }

    public function formatClosedAt(Order $order): string
    {
        if (! $order->order_closed_at) {
            return '';
        }

        $timezone = $order->address?->city?->city_timezone ?? config('app.timezone', 'Europe/Moscow');

        return $order->order_closed_at->copy()->timezone($timezone)->format('d.m.y H:i');
    }

    public function formatAmount(Order $order): string
    {
        $net = max(0, (int) $order->amount_paid - (int) $order->amount_comp);

        return number_format($net, 0, '', ' ').' р.';
    }

    public function callbackCell(Order $order): string
    {
        if (! in_array($order->order_status, ['callback', 'not_processed'], true)) {
            return '';
        }

        if (! $order->datetime_order) {
            return '●';
        }

        $timezone = $order->address?->city?->city_timezone ?? config('app.timezone', 'Europe/Moscow');

        return $order->datetime_order->copy()->timezone($timezone)->format('d.m.y H:i');
    }

    /** РК в КП — отметка претензии по заказу. */
    public function complaintMark(Order $order): string
    {
        return ($order->has_complaint ?? false) ? '!' : '';
    }

    public function rowStatusClass(Order $order): string
    {
        return match ($order->order_status) {
            'pending' => 'order-history-row--pending',
            'on_way' => 'order-history-row--on-way',
            'in_progress', 'in_progress_sd' => 'order-history-row--in-progress',
            'completed' => 'order-history-row--completed',
            'callback', 'not_processed' => 'order-history-row--callback',
            default => 'order-history-row--default',
        };
    }
}
