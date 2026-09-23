<?php

namespace App\Services\Hub;

use App\Models\Hub\HubOrderCache;
use App\Models\Hub\HubSourceConnection;
use App\Models\HubMasterLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Чтение заявок Единого хаба (кэш lead-desk) внутри Lead Control.
 *
 * Lead Control — единственная точка агрегации: GM ходит только в /api/v1/gm/*,
 * Desk-токен наружу не уходит. Источник читается только на чтение.
 */
class HubOrderRepository
{
    public const SOURCE_KP = HubOrderPresenter::SOURCE_KP;

    /** @var array<string, array<string, mixed>> */
    private array $cachedState = [];

    public function __construct(private HubOrderPresenter $presenter) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.unified_hub.enabled', true);
    }

    private function kpCrmId(): int
    {
        return (int) config('services.unified_hub.kp_crm_id', 2);
    }

    /**
     * Заявки KP-Lead конкретного мастера.
     *
     * @return array{ok: bool, linked: bool, items: list<array<string, mixed>>, source: array<string, mixed>}
     */
    public function ordersForMaster(User $user): array
    {
        // Свежесть считаем по городам мастера: отставание чужого города не должно
        // помечать его выборку как протухшую, и наоборот.
        $state = $this->sourceState($this->masterCityIds($user));

        if (! $this->isEnabled()) {
            return ['ok' => false, 'linked' => false, 'items' => [], 'source' => $state];
        }

        $identities = $this->identitiesForMaster($user);
        if ($identities['externalIds'] === [] && $identities['namePairs'] === []) {
            // Мастер не связан с KP — это не сбой источника, просто нет его заявок.
            return ['ok' => $state['available'], 'linked' => false, 'items' => [], 'source' => $state];
        }

        try {
            $rows = $this->baseQuery()
                ->where(function ($query) use ($identities) {
                    if ($identities['externalIds'] !== []) {
                        $query->orWhereIn('master_external_id', $identities['externalIds']);
                    }
                    foreach ($identities['namePairs'] as $pair) {
                        $query->orWhere(function ($sub) use ($pair) {
                            $sub->where('master_name', $pair['name']);
                            if ($pair['cityId'] !== null) {
                                $sub->where('city_id', $pair['cityId']);
                            }
                        });
                    }
                })
                ->orderByDesc('created_at_local')
                ->orderByDesc('id')
                ->get();
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'linked' => true,
                'items' => [],
                'source' => $this->unavailableState($exception),
            ];
        }

        $items = $rows
            ->map(fn (HubOrderCache $row) => $this->presenter->summary($row, (int) $user->user_id, self::SOURCE_KP))
            ->values()
            ->all();

        return ['ok' => $state['available'], 'linked' => true, 'items' => $items, 'source' => $state];
    }

    /**
     * Заявки KP-Lead филиала с разрешением мастера по карте соответствий.
     *
     * @return array{ok: bool, items: list<array<string, mixed>>, source: array<string, mixed>}
     */
    public function ordersForCity(int $cityId): array
    {
        $state = $this->sourceState([$cityId]);

        if (! $this->isEnabled()) {
            return ['ok' => false, 'items' => [], 'source' => $state];
        }

        try {
            $rows = $this->baseQuery()
                ->where('city_id', $cityId)
                ->orderByDesc('created_at_local')
                ->get();
        } catch (Throwable $exception) {
            return ['ok' => false, 'items' => [], 'source' => $this->unavailableState($exception)];
        }

        $resolver = $this->cityMasterResolver($cityId);

        $items = $rows
            ->map(fn (HubOrderCache $row) => $this->presenter->summary($row, $resolver($row), self::SOURCE_KP))
            ->values()
            ->all();

        return ['ok' => $state['available'], 'items' => $items, 'source' => $state];
    }

    /**
     * Карточка заявки источника — только если она принадлежит этому мастеру.
     *
     * @return array{found: bool, order: array<string, mixed>|null, source: array<string, mixed>, ok: bool}
     */
    public function findForMaster(User $user, string $source, string $externalId): array
    {
        $state = $this->sourceState($this->masterCityIds($user));

        if (! $this->isEnabled() || $source !== self::SOURCE_KP) {
            return ['found' => false, 'order' => null, 'source' => $state, 'ok' => false];
        }

        $identities = $this->identitiesForMaster($user);
        if ($identities['externalIds'] === [] && $identities['namePairs'] === []) {
            return ['found' => false, 'order' => null, 'source' => $state, 'ok' => $state['available']];
        }

        try {
            $row = $this->baseQuery()
                ->where('external_id', $externalId)
                ->where(function ($query) use ($identities) {
                    if ($identities['externalIds'] !== []) {
                        $query->orWhereIn('master_external_id', $identities['externalIds']);
                    }
                    foreach ($identities['namePairs'] as $pair) {
                        $query->orWhere(function ($sub) use ($pair) {
                            $sub->where('master_name', $pair['name']);
                            if ($pair['cityId'] !== null) {
                                $sub->where('city_id', $pair['cityId']);
                            }
                        });
                    }
                })
                ->first();
        } catch (Throwable $exception) {
            return ['found' => false, 'order' => null, 'source' => $this->unavailableState($exception), 'ok' => false];
        }

        if (! $row) {
            return ['found' => false, 'order' => null, 'source' => $state, 'ok' => $state['available']];
        }

        return [
            'found' => true,
            'order' => $this->presenter->details($row, (int) $user->user_id, self::SOURCE_KP),
            'source' => $state,
            'ok' => true,
        ];
    }

    /**
     * Состояние источника KP-Lead: доступность кэша и время последней синхронизации.
     *
     * @return array<string, mixed>
     */
    public function sourceState(?array $cityIds = null): array
    {
        $cacheKey = $cityIds === null ? 'all' : implode(',', $cityIds);
        if (isset($this->cachedState[$cacheKey])) {
            return $this->cachedState[$cacheKey];
        }

        if (! $this->isEnabled()) {
            return $this->cachedState[$cacheKey] = [
                'source' => self::SOURCE_KP,
                'name' => 'kp-lead-centre',
                'enabled' => false,
                'available' => false,
                'status' => 'disabled',
                'lastSyncAt' => null,
                'message' => 'Источник KP-Lead отключён настройкой UNIFIED_HUB_ENABLED.',
            ];
        }

        try {
            /** @var HubSourceConnection|null $connection */
            $connection = HubSourceConnection::query()->find($this->kpCrmId());

            // Для мастера важна свежесть синхронизации его городов, а не всего источника.
            $cacheQuery = $this->baseQuery();
            if ($cityIds !== null && $cityIds !== []) {
                $cacheQuery->whereIn('city_id', $cityIds);
            }
            $lastCacheSync = $cacheQuery->max('last_synced_at');
            $globalCacheSync = $cityIds === null || $cityIds === []
                ? $lastCacheSync
                : $this->baseQuery()->max('last_synced_at');
        } catch (Throwable $exception) {
            return $this->cachedState[$cacheKey] = $this->unavailableState($exception);
        }

        if (! $connection) {
            return $this->cachedState[$cacheKey] = [
                'source' => self::SOURCE_KP,
                'name' => 'kp-lead-centre',
                'enabled' => true,
                'available' => false,
                'status' => 'unavailable',
                'lastSyncAt' => null,
                'message' => 'Подключение KP-Lead не найдено в Едином окне.',
            ];
        }

        $scopedSyncAt = $this->parseHubTimestamp($lastCacheSync);
        $lastSyncAt = $scopedSyncAt
            ?? $this->latestTimestamp($connection->last_sync_at, $globalCacheSync);

        $staleAfter = (int) config('services.unified_hub.stale_after_minutes', 180);
        $isStale = $lastSyncAt === null
            || $lastSyncAt->lessThan(CarbonImmutable::now()->subMinutes($staleAfter));

        $status = 'ok';
        $message = null;
        if ($isStale) {
            $status = 'stale';
            $message = 'Синхронизация KP-Lead не обновлялась дольше '.$staleAfter.' мин.';
        } elseif ((string) $connection->status === 'error') {
            $status = 'degraded';
            $message = 'Единое окно сообщает об ошибке подключения KP-Lead (часть городов может отставать).';
        }

        return $this->cachedState[$cacheKey] = [
            'source' => self::SOURCE_KP,
            'name' => (string) ($connection->name ?: 'kp-lead-centre'),
            'enabled' => true,
            'available' => true,
            'status' => $status,
            'lastSyncAt' => $lastSyncAt?->setTimezone('Europe/Moscow')->toIso8601String(),
            'message' => $message,
        ];
    }

    /**
     * @return list<int>|null
     */
    private function masterCityIds(User $user): ?array
    {
        try {
            $ids = array_values(array_unique(array_map('intval', $user->cityIdsForOrdersFilter())));
        } catch (Throwable) {
            return null;
        }

        return $ids === [] ? null : $ids;
    }

    /**
     * Серверная метка Единого окна: она записана в часовом поясе того приложения (UTC),
     * а не в TZ Lead Control — иначе свежая синхронизация выглядит «протухшей».
     */
    private function parseHubTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tz = (string) config('services.unified_hub.timezone', 'UTC');

        try {
            return $value instanceof \DateTimeInterface
                ? CarbonImmutable::instance($value)->shiftTimezone($tz)
                : CarbonImmutable::parse((string) $value, $tz);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Время последней успешной синхронизации по городам (ТЗ: лог по источникам и городам).
     *
     * @param  list<int>|null  $cityIds
     * @return list<array{cityId: int|null, lastSyncAt: string|null, stale: bool, orders: int}>
     */
    public function citySyncState(?array $cityIds = null): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            $query = $this->baseQuery()
                ->selectRaw('city_id, MAX(last_synced_at) as last_sync, COUNT(*) as orders_count')
                ->groupBy('city_id');

            if ($cityIds !== null) {
                $query->whereIn('city_id', $cityIds === [] ? [-1] : $cityIds);
            }

            $staleAfter = (int) config('services.unified_hub.stale_after_minutes', 180);
            $staleBefore = CarbonImmutable::now()->subMinutes($staleAfter);

            return $query->get()
                ->map(function ($row) use ($staleBefore) {
                    $lastSync = $this->parseHubTimestamp($row->last_sync);

                    return [
                        'cityId' => $row->city_id !== null ? (int) $row->city_id : null,
                        'lastSyncAt' => $lastSync?->setTimezone('Europe/Moscow')->toIso8601String(),
                        'stale' => $lastSync === null || $lastSync->lessThan($staleBefore),
                        'orders' => (int) $row->orders_count,
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('hub.citySyncState failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * Идентификаторы мастера во внешних источниках: явная карта соответствий + kp_employee_id.
     *
     * @return array{externalIds: list<string>, namePairs: list<array{name: string, cityId: int|null}>}
     */
    public function identitiesForMaster(User $user): array
    {
        $externalIds = [];
        $namePairs = [];

        $kpEmployeeId = $user->getAttribute('kp_employee_id');
        if ($kpEmployeeId !== null && (string) $kpEmployeeId !== '' && (int) $kpEmployeeId > 0) {
            $externalIds[] = (string) $kpEmployeeId;
        }

        $links = HubMasterLink::query()
            ->where('source', self::SOURCE_KP)
            ->where('is_active', true)
            ->where('user_id', (int) $user->user_id)
            ->get();

        foreach ($links as $link) {
            if ($link->external_master_id !== null && $link->external_master_id !== '') {
                $externalIds[] = (string) $link->external_master_id;
            }
            $name = trim((string) $link->external_master_name);
            if ($name !== '') {
                $namePairs[] = ['name' => $name, 'cityId' => $link->city_id !== null ? (int) $link->city_id : null];
            }
        }

        return [
            'externalIds' => array_values(array_unique($externalIds)),
            'namePairs' => array_values(array_unique($namePairs, SORT_REGULAR)),
        ];
    }

    /**
     * @return \Closure(HubOrderCache): ?int
     */
    private function cityMasterResolver(int $cityId): \Closure
    {
        $links = HubMasterLink::query()
            ->where('source', self::SOURCE_KP)
            ->where('is_active', true)
            ->where(function ($query) use ($cityId) {
                $query->whereNull('city_id')->orWhere('city_id', $cityId);
            })
            ->get();

        $byExternalId = [];
        $byName = [];
        foreach ($links as $link) {
            if ($link->external_master_id !== null && $link->external_master_id !== '') {
                $byExternalId[(string) $link->external_master_id] = (int) $link->user_id;
            }
            $name = trim((string) $link->external_master_name);
            if ($name !== '') {
                $byName[$name] = (int) $link->user_id;
            }
        }

        $byKpEmployeeId = User::query()
            ->whereNotNull('kp_employee_id')
            ->pluck('user_id', 'kp_employee_id')
            ->mapWithKeys(fn ($userId, $kpId) => [(string) $kpId => (int) $userId])
            ->all();

        return function (HubOrderCache $row) use ($byExternalId, $byName, $byKpEmployeeId): ?int {
            $externalId = trim((string) ($row->master_external_id ?? ''));
            if ($externalId !== '') {
                if (isset($byExternalId[$externalId])) {
                    return $byExternalId[$externalId];
                }
                if (isset($byKpEmployeeId[$externalId])) {
                    return $byKpEmployeeId[$externalId];
                }
            }

            $name = trim((string) ($row->master_name ?? ''));
            if ($name !== '' && isset($byName[$name])) {
                return $byName[$name];
            }

            return null;
        };
    }

    private function baseQuery()
    {
        return HubOrderCache::query()->where('crm_id', $this->kpCrmId());
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableState(Throwable $exception): array
    {
        Log::warning('hub.source unavailable', [
            'source' => self::SOURCE_KP,
            'error' => $exception->getMessage(),
        ]);

        return [
            'source' => self::SOURCE_KP,
            'name' => 'kp-lead-centre',
            'enabled' => true,
            'available' => false,
            'status' => 'unavailable',
            'lastSyncAt' => null,
            'message' => 'Кэш Единого хаба временно недоступен — данные KP-Lead неполные.',
        ];
    }

    private function latestTimestamp(mixed ...$values): ?CarbonImmutable
    {
        $latest = null;
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parsed = $this->parseHubTimestamp($value);
            if ($parsed === null) {
                continue;
            }
            if ($latest === null || $parsed->greaterThan($latest)) {
                $latest = $parsed;
            }
        }

        return $latest;
    }
}
