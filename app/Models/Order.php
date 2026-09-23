<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    protected $primaryKey = 'order_id';

    public $timestamps = false;

    protected $fillable = [
        'datetime_order',
        'address_id',
        'order_status',
        'order_type',
        'order_core',
        'is_long_trip',
        'equipment_type',
        'amount_paid',
        'amount_comp',
        'master_id',
        'source_id',
        'order_adds',
        'shift_adds',
        'city_adds',
        'order_created_at',
        'order_closed_at',
        'status_changed_at',
        'in_progress_at',
        'partner_user_id',
        'sync_version',
    ];

    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * order_created_by, order_closed_by - автор/закрывший заказ
     */
    protected $casts = [
        'datetime_order' => 'datetime',
        'amount_paid' => 'integer',
        'amount_comp' => 'integer',
        'order_created_at' => 'datetime',
        'order_closed_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'master_handed_over_at' => 'datetime',
        'is_long_trip' => 'boolean',
    ];

    public const BRANCH_COMMENT_SD_HEADING = '**Отписка мастера при переводе в СД**';

    public const BRANCH_COMMENT_CLOSE_HEADING = '**Отписка мастера при закрытии заявки**';

    const EQUIPMENT_TYPES = [
        'tv' => 'Телевизоры',
        'computer' => 'Пк/Моноблоки/Ноутбуки',
        'printer' => 'Ремонт принтера/Настройка без пк/Прошивка принтера',
        'monitor' => 'Мониторы/Видеокарты (без ПК)',
        'boiler' => 'Бойлеры',
        'oven' => 'Духовки/Духовые шкафы/Электроплиты',
        'conditioner' => 'Кондиционеры/Сплит-системы',
        'coffee_machine' => 'Кофемашины',
        'dishwasher' => 'Посудомоечные машины',
        'washing_machine' => 'Стиральные/Сушильные машины',
        'fridge' => 'Холодильники/Морозилки',
        'game_console' => 'Чистка и настройка PS/Xbox/Nintendo switch/Steam Deck',
        'data_recovery' => 'Восстановление данных с носителей',
        'cable' => 'Обжим кабеля без пк',
        'router' => 'Роутеры',
        'smartphone' => 'Телефоны/Планшеты',
        'other_device' => 'Тип техники «ПРОЧАЯ»',
    ];

    /** Профильный вид техники по регламенту: только ПК/ноутбуки. */
    const CORE_EQUIPMENT = ['computer'];

    public const DISPATCHER_ARCHIVE_DAYS = 7;

    /**
     * Порог «Оплачено клиентом»: с этой суммы (включительно) при проведении
     * нужен хотя бы один документ. Ниже — документы не обязательны.
     */
    public const DOCUMENTS_REQUIRED_FROM_PAID = 3000;

    public static function documentsRequiredForPaidAmount(null|int|string $paidAmount): bool
    {
        return (int) $paidAmount >= self::DOCUMENTS_REQUIRED_FROM_PAID;
    }

    /** @return list<string> */
    public static function dispatcherArchiveStatusCodes(): array
    {
        return ['completed', 'rejected', 'cancelled_cc', 'cancelled_city'];
    }

    public static function dispatcherArchiveWindowStart(): Carbon
    {
        return now()->subDays(self::DISPATCHER_ARCHIVE_DAYS)->startOfSecond();
    }

    public function scopeVisibleWithinDispatcherArchiveWindow(Builder $query): Builder
    {
        $archive = self::dispatcherArchiveStatusCodes();
        $windowStart = self::dispatcherArchiveWindowStart();

        return $query->where(function (Builder $q) use ($archive, $windowStart) {
            $q->whereNotIn('order_status', $archive)
                ->orWhere(function (Builder $sub) use ($archive, $windowStart) {
                    $sub->whereIn('order_status', $archive)
                        ->whereRaw('COALESCE(order_closed_at, order_created_at) >= ?', [$windowStart]);
                });
        });
    }

    public function scopeOnlyDispatcherArchiveWithinWindow(Builder $query): Builder
    {
        return $query
            ->whereIn('order_status', self::dispatcherArchiveStatusCodes())
            ->whereRaw('COALESCE(order_closed_at, order_created_at) >= ?', [self::dispatcherArchiveWindowStart()]);
    }

    /** Все коды статусов заказа (для ролей с полной видимостью) */
    public static function allStatusCodes(): array
    {
        return [
            'pending', 'callback', 'not_processed', 'rejected',
            'on_way', 'in_progress', 'in_progress_sd', 'review',
            'completed', 'cancelled_cc', 'cancelled_city',
        ];
    }

    /**
     * Приоритет статуса в списке заказов (меньше — выше).
     * Ожидает → В пути → В работе → В работе СД → (Готов/отмены/отказ) → Прозвон → Не оформлена.
     */
    public static function listStatusSortPriority(): array
    {
        return [
            'pending' => 1,
            'on_way' => 2,
            'in_progress' => 3,
            'in_progress_sd' => 4,
            'review' => 5,
            'completed' => 5,
            'cancelled_cc' => 5,
            'cancelled_city' => 5,
            'rejected' => 5,
            'callback' => 6,
            'not_processed' => 7,
        ];
    }

    /**
     * Приоритет статуса в списке заказов для диспетчеров (меньше — выше).
     * Прозвон → в пути → в работе → в работе СД → … → не оформлена в конце.
     */
    public static function dispatcherListStatusSortPriority(): array
    {
        return [
            'callback' => 1,
            'on_way' => 2,
            'in_progress' => 3,
            'in_progress_sd' => 4,
            'pending' => 5,
            'review' => 6,
            'completed' => 7,
            'rejected' => 7,
            'cancelled_cc' => 7,
            'cancelled_city' => 7,
            'not_processed' => 8,
        ];
    }

    /** Сортировка активных заявок на главной диспетчера. */
    public static function dispatcherActiveStatusSortPriority(): array
    {
        return [
            'callback' => 1,
            'on_way' => 2,
            'in_progress' => 3,
            'in_progress_sd' => 4,
            'pending' => 5,
            'review' => 6,
            'not_processed' => 7,
        ];
    }

    /** @return list<string> */
    public static function dispatcherCallbackStatusCodes(): array
    {
        return ['callback', 'not_processed'];
    }

    /** @return list<string> */
    public static function dispatcherInWorkStatusCodes(): array
    {
        return ['in_progress_sd', 'on_way', 'pending', 'in_progress', 'review'];
    }

    /** @return list<string> */
    public static function dispatcherActiveStatusCodes(): array
    {
        return array_merge(
            self::dispatcherCallbackStatusCodes(),
            self::dispatcherInWorkStatusCodes()
        );
    }

    public static function dispatcherInWorkStatusSortPriority(): array
    {
        return [
            'in_progress_sd' => 1,
            'on_way' => 2,
            'pending' => 3,
            'in_progress' => 4,
            'review' => 5,
        ];
    }

    public function scopeOrderByDispatcherInWork(Builder $query, string $datetimeDir = 'asc'): Builder
    {
        $datetimeDir = strtolower($datetimeDir) === 'desc' ? 'desc' : 'asc';

        $when = [];
        $bindings = [];
        foreach (self::dispatcherInWorkStatusSortPriority() as $status => $priority) {
            $when[] = 'WHEN order_status = ? THEN ?';
            $bindings[] = $status;
            $bindings[] = $priority;
        }

        $sql = 'CASE '.implode(' ', $when).' ELSE 99 END';

        return $query
            ->orderByRaw($sql.' ASC', $bindings)
            ->orderBy('datetime_order', $datetimeDir)
            ->orderBy('order_id', $datetimeDir);
    }

    public function scopeOrderByDispatcherActive(Builder $query, string $datetimeDir = 'asc'): Builder
    {
        $datetimeDir = strtolower($datetimeDir) === 'desc' ? 'desc' : 'asc';

        $when = [];
        $bindings = [];
        foreach (self::dispatcherActiveStatusSortPriority() as $status => $priority) {
            $when[] = 'WHEN order_status = ? THEN ?';
            $bindings[] = $status;
            $bindings[] = $priority;
        }

        $sql = 'CASE '.implode(' ', $when).' ELSE 99 END';

        return $query
            ->orderByRaw($sql.' ASC', $bindings)
            ->orderBy('datetime_order', $datetimeDir)
            ->orderBy('order_id', $datetimeDir);
    }

    public function scopeFilterBySource(Builder $query, mixed $sourceIds = null, mixed $sourceFormats = null): Builder
    {
        $sourceIds = array_values(array_filter(array_map('intval', (array) $sourceIds)));
        $sourceFormats = array_values(array_filter((array) $sourceFormats));

        if ($sourceIds !== []) {
            $query->whereIn($query->getModel()->getTable().'.source_id', $sourceIds);
        }

        if ($sourceFormats !== []) {
            $query->whereHas('source', fn (Builder $q) => $q->whereIn('source_format', $sourceFormats));
        }

        return $query;
    }

    public function scopeOrderByListDefault(Builder $query, string $datetimeDir = 'asc', ?array $statusPriority = null): Builder
    {
        $datetimeDir = strtolower($datetimeDir) === 'desc' ? 'desc' : 'asc';

        $when = [];
        $bindings = [];
        foreach ($statusPriority ?? self::listStatusSortPriority() as $status => $priority) {
            $when[] = 'WHEN order_status = ? THEN ?';
            $bindings[] = $status;
            $bindings[] = $priority;
        }

        $sql = 'CASE '.implode(' ', $when).' ELSE 99 END';

        return $query
            ->orderByRaw($sql.' ASC', $bindings)
            ->orderBy('datetime_order', $datetimeDir)
            ->orderBy('order_id', $datetimeDir);
    }

    public function scopeOrderByDispatcherListDefault(Builder $query, string $datetimeDir = 'asc'): Builder
    {
        return $query->orderByListDefault($datetimeDir, self::dispatcherListStatusSortPriority());
    }

    /** Подписи статусов для селектов и фильтров */
    public static function getStatusLabels(): array
    {
        return [
            'pending' => 'Ожидание',
            'callback' => 'Прозвон',
            'not_processed' => 'Не оформлена',
            'rejected' => 'Отказ',
            'on_way' => 'В пути',
            'in_progress' => 'В работе',
            'in_progress_sd' => 'В работе (СД)',
            'review' => 'Проверка',
            'completed' => 'Готов',
            'cancelled_cc' => 'Отмена КЦ',
            'cancelled_city' => 'Отмена Филиала',
            // Устаревшие коды БД (не в фильтрах; подпись если заказ ещё не мигрирован)
            'waiting_parts' => 'Ожидание запчастей',
            'waiting_payment' => 'Ожидание оплаты',
        ];
    }

    public static function getOrderCoreByEquipment(string $equipmentType): string
    {
        if ($equipmentType === 'other_device') {
            return 'other';
        }

        return in_array($equipmentType, self::CORE_EQUIPMENT) ? 'core' : 'non_core';
    }

    public static function orderCoreLabel(?string $orderCore): string
    {
        return match ($orderCore) {
            'core' => 'Профильный',
            'non_core' => 'Непрофильный',
            'other' => 'Прочий (наш 50% / партнёр 40%)',
            default => $orderCore ?: '—',
        };
    }

    /**
     * CSS-класс срочности события на главной:
     * «Ожидание» / «Прозвон» — мигание красным за 30 мин до datetime_order;
     * просроченный прозвон — сплошная подсветка.
     * «В пути» / «В работе» — без подсветки.
     */
    public function eventUrgencyClass(): string
    {
        if (! in_array($this->order_status, ['pending', 'callback'], true) || ! $this->datetime_order) {
            return '';
        }

        $cityTimezone = $this->address?->city?->city_timezone ?? config('app.timezone', 'Europe/Moscow');
        // datetime_order хранится как «настенные часы» города — shiftTimezone, без конвертации
        $orderInCity = $this->datetime_order->copy()->shiftTimezone($cityTimezone);
        $nowLocal = now($cityTimezone);
        $secondsToOrder = $orderInCity->getTimestamp() - $nowLocal->getTimestamp();

        if ($secondsToOrder >= 0 && $secondsToOrder <= 1800) {
            return 'blink-red';
        }

        if ($this->order_status === 'callback' && $secondsToOrder < 0) {
            return 'callback-overdue';
        }

        return '';
    }

    /** Тип заявки для GM: first | repeat | warranty (CRM `new` → first). */
    public function orderKind(): string
    {
        return match ($this->order_type) {
            'repeat' => 'repeat',
            'warranty' => 'warranty',
            default => 'first',
        };
    }

    public function appendBranchCommentBlock(string $heading, string $body): void
    {
        $body = trim($body);
        $block = $heading."\n".$body;
        $existing = trim((string) ($this->city_adds ?? ''));
        $this->city_adds = $existing === '' ? $block : $existing."\n\n".$block;
    }

    public function extractBranchCommentBlock(string $heading): ?string
    {
        $text = (string) ($this->city_adds ?? '');
        if ($text === '' || ! str_contains($text, $heading)) {
            return null;
        }

        $parts = preg_split('/\n\n(?=\*\*)/u', $text) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (! str_starts_with($part, $heading)) {
                continue;
            }
            $lines = preg_split("/\r\n|\n|\r/", $part) ?: [];
            array_shift($lines);

            $content = trim(implode("\n", $lines));

            return $content === '' ? null : $content;
        }

        return null;
    }

    /** Заказ в городе-спутнике — расчёт 50/50 (авто, без галочки). */
    public function isSatelliteCityOrder(): bool
    {
        $this->loadMissing(['address.city.parentCity', 'persons.addresses.city']);

        $addressCity = $this->address?->city;
        if ($addressCity?->isSatellite()) {
            return true;
        }

        if ($addressCity) {
            $groupIds = City::operationGroupIds((int) $addressCity->city_id);

            foreach ($this->persons as $person) {
                foreach ($person->addresses as $personAddress) {
                    if (
                        $personAddress->city?->isSatellite()
                        && in_array((int) $personAddress->city_id, $groupIds, true)
                    ) {
                        return true;
                    }
                }
            }

            // Текстовый маркер спутника в комментариях — для материнского города кластера
            $rootId = $addressCity->parent_city_id
                ? (int) $addressCity->parent_city_id
                : (int) $addressCity->city_id;

            if ((int) $addressCity->city_id === $rootId && City::satelliteNamesForParent($rootId) !== []) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $this->city_adds,
                    $this->order_adds,
                    $this->address->address_adds ?? '',
                    $this->address->street ?? '',
                ])));

                foreach (City::satelliteNamesForParent($rootId) as $satelliteName) {
                    if ($satelliteName !== '' && str_contains($haystack, $satelliteName)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_created_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_closed_by');
    }

    public function persons(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'order_persons', 'order_id', 'person_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'order_id', 'order_id');
    }

    public function cfmOperations(): HasMany
    {
        return $this->hasMany(CfmOperation::class, 'related_order_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Первые просмотры карточки заказа сотрудниками филиала (senior_manager, branch_head).
     */
    public function cityViews(): HasMany
    {
        return $this->hasMany(OrderCityView::class, 'order_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(OrderActivityLog::class, 'order_id')->orderByDesc('created_at');
    }

    /**
     * Проверить, закрыт ли заказ
     */
    public function isClosedStatus(): bool
    {
        return in_array($this->order_status, ['completed', 'cancelled_cc', 'cancelled_city']);
    }

    /** Партнёрский заказ (SuperPart или источник из каталога партнёров). */
    public function isPartnerOrder(): bool
    {
        if ($this->partner_user_id) {
            return true;
        }

        $source = $this->relationLoaded('source') ? $this->source : $this->source()->first();

        return $source && ($source->superpart_partner_id || $source->available_for_superpart);
    }

    /** Наш листовочный / не партнёрский заказ. */
    public function isFlyerOrder(): bool
    {
        return ! $this->isPartnerOrder();
    }

    /**
     * Человекочитаемый статус: сначала подписи из getStatusLabels() (как в селектах и списке заказов),
     * затем устаревшие коды БД (unassigned, on_way, waiting_parts, waiting_payment).
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = self::getStatusLabels();
        if ($this->order_status !== null && array_key_exists($this->order_status, $labels)) {
            return $labels[$this->order_status];
        }

        return match ($this->order_status) {
            'unassigned' => 'Без мастера',
            'on_way' => 'В пути',
            'waiting_parts' => 'Ожидание запчастей',
            'waiting_payment' => 'Ожидание оплаты',
            default => $this->order_status ?? 'Неизвестно',
        };
    }
}
