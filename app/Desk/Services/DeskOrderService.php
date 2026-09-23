<?php

namespace App\Desk\Services;

use App\Models\DeskLog;
use App\Models\DeskOrderCache;
use App\Models\DeskStat;
use App\Models\DeskStatusMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DeskOrderService
{
    public function __construct(
        protected DeskSyncService $sync
    ) {}

    public function baseQueryForUser(User $user): Builder
    {
        $query = DeskOrderCache::query()
            ->with(['connection', 'city'])
            ->where('status', '!=', 'closed');

        $cityIds = $user->cityIdsForOrdersFilter();
        if ($cityIds === []) {
            $query->whereRaw('0 = 1');
        } elseif (is_array($cityIds)) {
            $query->whereIn('city_id', $cityIds);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(User $user): array
    {
        $rows = $this->baseQueryForUser($user)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $out = [];
        foreach (array_keys(DeskOrderCache::unifiedStatusLabels()) as $code) {
            $out[$code] = (int) ($rows[$code] ?? 0);
        }

        return $out;
    }

    public function findForUser(User $user, int $cacheId): DeskOrderCache
    {
        $order = $this->baseQueryForUser($user)->whereKey($cacheId)->first();
        if (! $order) {
            abort(404);
        }

        return $order;
    }

    /**
     * @param  array{raw_status?: ?string, paid_amount?: ?int, parts_amount?: ?int, comment?: ?string}  $data
     */
    public function update(DeskOrderCache $cached, array $data, User $user): DeskOrderCache
    {
        $connection = $cached->connection;
        if ($connection->status === 'error') {
            throw new RuntimeException('CRM недоступна: '.$connection->last_error);
        }

        $adapter = $this->sync->makeAdapter($connection);
        $snapshot = $cached->replicate();

        try {
            $adapter->updateOrder($cached->external_id, $data);

            return $this->sync->refreshCachedOrder($cached->fresh());
        } catch (Throwable $e) {
            DeskLog::write('error', $e->getMessage(), $connection->id, 'update', $cached->external_id, [
                'user_id' => $user->user_id ?? $user->getKey(),
            ]);
            // Локальный кэш не трогали до refresh — откат не нужен; snapshot на будущее
            unset($snapshot);
            throw $e;
        }
    }

    public function close(DeskOrderCache $cached, User $user): void
    {
        $this->assertCloseable($cached);

        $connection = $cached->connection;
        if ($connection->status === 'error') {
            throw new RuntimeException('CRM недоступна: '.$connection->last_error);
        }

        $adapter = $this->sync->makeAdapter($connection);

        try {
            DB::transaction(function () use ($adapter, $cached, $connection) {
                $externalId = $cached->external_id;
                $adapter->closeOrder($externalId);
                $cached->delete();
                DeskStat::incrementKey('closed_via_desk');
                DeskLog::write('info', 'Заказ закрыт через Единое окно', $connection->id, 'close', $externalId);
            });
        } catch (Throwable $e) {
            DeskLog::write('error', $e->getMessage(), $connection->id, 'close', $cached->external_id, [
                'user_id' => $user->user_id ?? $user->getKey(),
            ]);
            throw $e;
        }
    }

    public function assertCloseable(DeskOrderCache $cached): void
    {
        $errors = [];

        if (! filled(trim((string) $cached->comments)) && ! filled(trim((string) $cached->description))) {
            $errors[] = 'Нужен комментарий / описание';
        }

        if ($cached->paid_amount === null) {
            $errors[] = 'Заполните «Оплачено»';
        }

        if ($cached->parts_amount === null) {
            $errors[] = 'Заполните «Запчасти»';
        }

        $docs = $cached->documents ?? [];
        $paid = (int) ($cached->paid_amount ?? 0);
        if ($paid >= 3000 && (! is_array($docs) || count($docs) < 1)) {
            $errors[] = 'При сумме от 3000 ₽ нужен хотя бы один документ (можно загрузить в карточке CRM)';
        }

        if ($errors !== []) {
            throw new RuntimeException(implode('. ', $errors));
        }
    }

    public function mapRawToUnified(int $crmId, string $rawStatus): string
    {
        $map = DeskStatusMapping::query()
            ->where('crm_id', $crmId)
            ->where('crm_status_code', $rawStatus)
            ->first();

        return $map?->unified_status ?? 'in_progress';
    }
}
