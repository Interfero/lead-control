<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderActivityLog;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrderActivityLogService
{
    /** Роли, чьи правки по умолчанию не попадают в журнал (кроме сумм после переоткрытия). */
    private const DIRECTOR_ROLES = ['general_director', 'regional_director'];

    private const FIELD_LABELS = [
        'order_status' => 'Статус',
        'order_type' => 'Тип',
        'order_core' => 'Профильность',
        'is_long_trip' => 'Дальний выезд',
        'equipment_type' => 'Вид техники',
        'datetime_order' => 'Дата и время встречи',
        'order_adds' => 'Описание',
        'shift_adds' => 'Переносы',
        'city_adds' => 'Коммент филиала / отписка',
        'master_id' => 'Мастер',
        'source_id' => 'Источник',
        'amount_paid' => 'Оплачено',
        'amount_comp' => 'Комплектующие',
    ];

    public function ensureTable(): void
    {
        if (Schema::hasTable('order_activity_logs')) {
            return;
        }

        Schema::create('order_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 64);
            $table->string('field_name', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function loadLogsForOrder(Order $order): void
    {
        if (! Schema::hasTable('order_activity_logs')) {
            try {
                $this->ensureTable();
            } catch (\Throwable) {
                $order->setRelation('activityLogs', collect());

                return;
            }
        }

        $order->loadMissing('activityLogs.user.roles');
        $order->setRelation('activityLogs', $this->filterVisibleLogs($order));
    }

    /**
     * @return Collection<int, OrderActivityLog>
     */
    public function filterVisibleLogs(Order $order): Collection
    {
        if (! $order->relationLoaded('activityLogs')) {
            $order->loadMissing('activityLogs.user.roles');
        }

        $wasReopened = $this->orderWasReopened($order);

        return $order->activityLogs->filter(function (OrderActivityLog $log) use ($wasReopened) {
            if ($log->action !== 'updated') {
                return true;
            }

            $author = $log->user;
            if (! $author || ! $author->hasAnyRole(self::DIRECTOR_ROLES)) {
                return true;
            }

            if ($wasReopened && in_array($log->field_name, ['amount_paid', 'amount_comp'], true)) {
                return true;
            }

            return false;
        })->values();
    }

    public function canViewActivityLog(User $user): bool
    {
        return $user->hasAnyRole([
            'developer',
            'senior_dispatcher',
            'call_center',
            'regional_director',
            'senior_manager',
            'branch_head',
            'general_director',
        ]);
    }

    public function canViewClientHistory(User $user): bool
    {
        return $user->hasAnyRole([
            'developer',
            'call_center',
            'senior_dispatcher',
            'senior_manager',
            'tech_director',
            'branch_head',
            'regional_director',
            'general_director',
        ]);
    }

    public function log(Order $order, ?User $user, string $action, ?string $field = null, mixed $old = null, mixed $new = null): void
    {
        $this->ensureTable();

        OrderActivityLog::create([
            'order_id' => $order->order_id,
            'user_id' => $user?->user_id,
            'action' => $action,
            'field_name' => $field,
            'old_value' => is_string($old) ? $old : $this->formatFieldValue($field, $old, $order),
            'new_value' => is_string($new) ? $new : $this->formatFieldValue($field, $new, $order),
            'created_at' => now(),
        ]);
    }

    public function logCreated(Order $order, User $user): void
    {
        $order->loadMissing(['address.city', 'source', 'persons']);

        $this->log($order, $user, 'created', null, null, $this->buildCreationSummary($order));
    }

    public function logChanges(Order $order, User $user, array $before, array $after): void
    {
        $wasReopened = $this->orderWasReopened($order);

        foreach ($after as $field => $newValue) {
            if (! array_key_exists($field, $before)) {
                continue;
            }

            $oldValue = $before[$field];
            if ($this->stringify($oldValue) === $this->stringify($newValue)) {
                continue;
            }

            if (! $this->shouldLogUserChange($user, $field, $wasReopened)) {
                continue;
            }

            $action = $field === 'datetime_order' ? 'shift' : 'updated';
            $this->log($order, $user, $action, $field, $oldValue, $newValue);
        }
    }

    public function logCompleted(Order $order, User $user): void
    {
        $net = app(OrderService::class)->getNetAmount($order);
        $summary = sprintf(
            'Оплачено: %s · Комплектующие: %s · Чистыми: %s',
            $this->formatMoney($order->amount_paid),
            $this->formatMoney($order->amount_comp),
            $this->formatMoney($net)
        );

        $this->log($order, $user, 'completed', null, null, $summary);
    }

    public function shouldLogUserChange(User $user, string $field, bool $wasReopened): bool
    {
        if (! $user->hasAnyRole(self::DIRECTOR_ROLES)) {
            return true;
        }

        return $wasReopened && in_array($field, ['amount_paid', 'amount_comp'], true);
    }

    public function fieldLabel(?string $field): string
    {
        if ($field === null) {
            return '';
        }

        return self::FIELD_LABELS[$field] ?? $field;
    }

    public function formatFieldValue(?string $field, mixed $value, ?Order $order = null): ?string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'order_status') {
            $labels = Order::getStatusLabels();

            return $labels[$value] ?? (string) $value;
        }

        if ($field === 'order_type') {
            return match ((string) $value) {
                'new' => 'Впервые',
                'repeat' => 'Повтор',
                'warranty' => 'Гарантия',
                default => (string) $value,
            };
        }

        if ($field === 'order_core') {
            return match ((string) $value) {
                'core' => 'Профильный',
                'non_core' => 'Непрофильный',
                'other' => 'Другое',
                default => (string) $value,
            };
        }

        if ($field === 'is_long_trip') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Да' : 'Нет';
        }

        if ($field === 'equipment_type') {
            return Order::EQUIPMENT_TYPES[$value] ?? (string) $value;
        }

        if ($field === 'master_id') {
            return User::query()->where('user_id', $value)->value('user_name') ?? (string) $value;
        }

        if ($field === 'source_id') {
            return Source::query()->where('source_id', $value)->value('source_name') ?? (string) $value;
        }

        if ($field === 'datetime_order') {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('d.m.Y H:i');
            }

            try {
                return \Carbon\Carbon::parse((string) $value)->format('d.m.Y H:i');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        if (in_array($field, ['amount_paid', 'amount_comp'], true)) {
            return $this->formatMoney($value);
        }

        return $this->stringify($value);
    }

    private function buildCreationSummary(Order $order): string
    {
        $person = $order->persons->first();
        $lines = array_filter([
            'Тип: '.$this->formatFieldValue('order_type', $order->order_type),
            'Статус: '.$this->formatFieldValue('order_status', $order->order_status),
            'Встреча: '.$this->formatFieldValue('datetime_order', $order->datetime_order),
            $order->source_id ? 'Источник: '.$this->formatFieldValue('source_id', $order->source_id) : null,
            $person?->person_name ? 'Клиент: '.$person->person_name : null,
            $order->address?->city?->city_name ? 'Город: '.$order->address->city->city_name : null,
            $order->order_adds ? 'Описание: '.$order->order_adds : null,
        ]);

        return implode("\n", $lines);
    }

    private function orderWasReopened(Order $order): bool
    {
        return OrderActivityLog::query()
            ->where('order_id', $order->order_id)
            ->where('action', 'reopened')
            ->exists();
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((int) $value, 0, '', ' ').' ₽';
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }

        return (string) $value;
    }
}
