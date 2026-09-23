<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

class OrderRescheduleLog
{
    /**
     * Формат для лога переноса — те же «настенные часы», что в поле datetime_order на карточке
     * (без timezone(), иначе Asia/Almaty → Europe/Moscow даёт −2 ч и в логе всегда «10:00»).
     */
    public static function formatDatetime(?Carbon $dt, ?string $timezone = null): string
    {
        if ($dt === null) {
            return '—';
        }

        return $dt->format('d.m.Y/H:i');
    }

    public static function roleLabel(User $user): string
    {
        $priority = [
            'general_director' => 'Директор',
            'regional_director' => 'Директор',
            'branch_head' => 'Директор',
            'senior_dispatcher' => 'Ст дисп',
            'call_center' => 'Дисп',
            'developer' => 'Разраб',
        ];

        foreach ($priority as $code => $label) {
            if ($user->hasRole($code)) {
                return $label;
            }
        }

        $roleName = $user->roles->first()?->role_name;

        return $roleName !== null && $roleName !== '' ? $roleName : 'Сотрудник';
    }

    /**
     * Добавляет строку переноса в shift_adds при смене datetime_order.
     */
    public static function appendOnDatetimeChange(
        Order $order,
        ?Carbon $oldDatetime,
        Carbon $newDatetime,
        User $user,
        ?string $currentShiftAdds = null
    ): ?string {
        if ($oldDatetime === null) {
            return null;
        }

        $timezone = $order->address?->city?->city_timezone ?? config('app.timezone', 'Europe/Moscow');

        if (self::wallClockKey($oldDatetime) === self::wallClockKey($newDatetime)) {
            return null;
        }

        $line = sprintf(
            'Перенос с «%s» на «%s» %s',
            self::formatDatetime($oldDatetime, $timezone),
            self::formatDatetime($newDatetime, $timezone),
            self::roleLabel($user)
        );

        $base = trim((string) ($currentShiftAdds ?? $order->shift_adds ?? ''));

        return $base === '' ? $line : $base."\n".$line;
    }

    /** Ключ «настенных часов» — цифры как в форме, без сдвига TZ. */
    public static function wallClockKey(?Carbon $dt, ?string $timezone = null): ?string
    {
        if ($dt === null) {
            return null;
        }

        return $dt->format('Y-m-d H:i');
    }

    /** Значение из datetime-local — те же настенные часы, что хранятся в datetime_order. */
    public static function parseSubmittedDatetime(string $value, ?string $timezone = null): Carbon
    {
        return Carbon::parse($value, config('app.timezone', 'Europe/Moscow'));
    }
}
