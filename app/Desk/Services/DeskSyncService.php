<?php

namespace App\Desk\Services;

use App\Desk\Adapters\Crm1EloquentAdapter;
use App\Desk\Adapters\Crm2HttpAdapter;
use App\Desk\Contracts\CrmAdapter;
use App\Models\CrmConnection;
use App\Models\DeskLog;
use App\Models\DeskOrderCache;
use App\Models\DeskStatusMapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DeskSyncService
{
    public function makeAdapter(CrmConnection $connection): CrmAdapter
    {
        return match ($connection->type) {
            'crm1_eloquent', 'crm1_api' => new Crm1EloquentAdapter($connection),
            'crm2_http' => new Crm2HttpAdapter($connection),
            default => throw new RuntimeException("Неизвестный тип адаптера: {$connection->type}"),
        };
    }

    /**
     * @return array{upserted: int, removed: int}
     */
    public function syncConnection(CrmConnection $connection, bool $full = false): array
    {
        $adapter = $this->makeAdapter($connection);

        try {
            $adapter->authenticate();

            $filters = ['full' => $full];
            if (! $full && $connection->last_sync_at) {
                $filters['updated_after'] = $connection->last_sync_at;
            }

            $orders = $adapter->fetchOrders($filters);
            $mappings = $this->mappingsFor($connection->id);
            $seenExternalIds = [];
            $upserted = 0;

            DB::transaction(function () use ($connection, $orders, $mappings, &$seenExternalIds, &$upserted) {
                foreach ($orders as $payload) {
                    $raw = $payload['raw_status'] ?? '';
                    $map = $mappings[$raw] ?? null;
                    $unified = $map['unified_status'] ?? $this->fallbackUnified($raw);
                    $isClosed = (bool) ($map['is_closed'] ?? in_array($raw, ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'], true));

                    $seenExternalIds[] = (string) $payload['external_id'];

                    if ($isClosed || $unified === 'closed') {
                        DeskOrderCache::query()
                            ->where('crm_id', $connection->id)
                            ->where('external_id', $payload['external_id'])
                            ->delete();
                        continue;
                    }

                    DeskOrderCache::query()->updateOrCreate(
                        [
                            'crm_id' => $connection->id,
                            'external_id' => (string) $payload['external_id'],
                        ],
                        [
                            'city_id' => $payload['city_id'] ?? null,
                            'status' => $unified,
                            'raw_status' => $raw,
                            'client_name' => $payload['client_name'] ?? null,
                            'phone' => $payload['phone'] ?? null,
                            'address' => $payload['address'] ?? null,
                            'description' => $payload['description'] ?? null,
                            'master_name' => $payload['master_name'] ?? null,
                            'total_amount' => $payload['total_amount'] ?? null,
                            'paid_amount' => $payload['paid_amount'] ?? null,
                            'parts_amount' => $payload['parts_amount'] ?? null,
                            'created_at_local' => $payload['created_at_local'] ?? null,
                            'call_at_local' => $payload['call_at_local'] ?? null,
                            'timezone' => $payload['timezone'] ?? null,
                            'updated_at_local' => $payload['updated_at_local'] ?? null,
                            'order_type' => $payload['order_type'] ?? null,
                            'gm_status' => $payload['gm_status'] ?? null,
                            'comments' => $payload['comments'] ?? null,
                            'documents' => $payload['documents'] ?? [],
                            'priority' => $payload['priority'] ?? 0,
                            'row_highlight' => $payload['row_highlight'] ?? null,
                            'hash' => $payload['hash'] ?? null,
                            'last_synced_at' => now(),
                        ]
                    );
                    $upserted++;
                }
            });

            $removed = 0;
            if ($full) {
                $removed = DeskOrderCache::query()
                    ->where('crm_id', $connection->id)
                    ->when($seenExternalIds !== [], fn ($q) => $q->whereNotIn('external_id', $seenExternalIds))
                    ->delete();
            } elseif ($connection->type === 'crm1_eloquent') {
                // Инкремент: вычистить из кэша то, что уже закрыто в CRM1
                $cachedIds = DeskOrderCache::query()
                    ->where('crm_id', $connection->id)
                    ->pluck('external_id')
                    ->all();
                if ($cachedIds !== []) {
                    $stillOpen = \App\Models\Order::query()
                        ->whereIn('order_id', $cachedIds)
                        ->whereNotIn('order_status', ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'])
                        ->pluck('order_id')
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $toRemove = array_diff($cachedIds, $stillOpen);
                    if ($toRemove !== []) {
                        $removed = DeskOrderCache::query()
                            ->where('crm_id', $connection->id)
                            ->whereIn('external_id', $toRemove)
                            ->delete();
                    }
                }
            }

            $connection->markSynced();

            DeskLog::write('info', "Синхронизация OK: upserted={$upserted}, removed={$removed}", $connection->id, 'sync', null, [
                'full' => $full,
                'fetched' => count($orders),
            ]);

            return ['upserted' => $upserted, 'removed' => $removed];
        } catch (Throwable $e) {
            $connection->markError($e->getMessage());
            DeskLog::write('error', $e->getMessage(), $connection->id, 'sync', null, [
                'exception' => class_basename($e),
            ]);
            throw $e;
        }
    }

    /**
     * Горячее обновление одной карточки из родной CRM.
     */
    public function refreshCachedOrder(DeskOrderCache $cached): DeskOrderCache
    {
        $connection = $cached->connection;
        $adapter = $this->makeAdapter($connection);
        $payload = $adapter->fetchOrder($cached->external_id);

        if (! $payload) {
            $cached->delete();
            throw new RuntimeException('Заказ больше не найден в родной CRM');
        }

        $mappings = $this->mappingsFor($connection->id);
        $raw = $payload['raw_status'] ?? '';
        $map = $mappings[$raw] ?? null;
        $unified = $map['unified_status'] ?? $this->fallbackUnified($raw);
        $isClosed = (bool) ($map['is_closed'] ?? false);

        if ($isClosed || $unified === 'closed') {
            $cached->delete();
            throw new RuntimeException('Заказ уже закрыт в родной CRM');
        }

        $cached->fill([
            'city_id' => $payload['city_id'] ?? null,
            'status' => $unified,
            'raw_status' => $raw,
            'client_name' => $payload['client_name'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'address' => $payload['address'] ?? null,
            'description' => $payload['description'] ?? null,
            'master_name' => $payload['master_name'] ?? null,
            'total_amount' => $payload['total_amount'] ?? null,
            'paid_amount' => $payload['paid_amount'] ?? null,
            'parts_amount' => $payload['parts_amount'] ?? null,
            'created_at_local' => $payload['created_at_local'] ?? null,
            'call_at_local' => $payload['call_at_local'] ?? null,
            'timezone' => $payload['timezone'] ?? null,
            'updated_at_local' => $payload['updated_at_local'] ?? null,
            'order_type' => $payload['order_type'] ?? null,
            'comments' => $payload['comments'] ?? null,
            'documents' => $payload['documents'] ?? [],
            'priority' => $payload['priority'] ?? 0,
            'row_highlight' => $payload['row_highlight'] ?? null,
            'hash' => $payload['hash'] ?? null,
            'last_synced_at' => now(),
        ]);
        $cached->save();

        return $cached->fresh(['connection', 'city']);
    }

    /**
     * @return array<string, array{unified_status: string, is_closed: bool}>
     */
    protected function mappingsFor(int $crmId): array
    {
        return DeskStatusMapping::query()
            ->where('crm_id', $crmId)
            ->get()
            ->mapWithKeys(fn (DeskStatusMapping $m) => [
                $m->crm_status_code => [
                    'unified_status' => $m->unified_status,
                    'is_closed' => $m->is_closed,
                ],
            ])
            ->all();
    }

    protected function fallbackUnified(string $raw): string
    {
        return match ($raw) {
            'callback', 'not_processed', 'pending' => 'new',
            'on_way' => 'on_way',
            'in_progress', 'in_progress_sd', 'review' => 'in_progress',
            'completed' => 'ready',
            'cancelled_cc', 'cancelled_city', 'rejected' => 'closed',
            default => 'in_progress',
        };
    }
}
