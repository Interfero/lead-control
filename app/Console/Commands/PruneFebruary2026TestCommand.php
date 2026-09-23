<?php

namespace App\Console\Commands;

use App\Models\CfmOperation;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneFebruary2026TestCommand extends Command
{
    protected $signature = 'app:prune-february-2026-test
                            {--dry-run : Только показать план}
                            {--force : Без подтверждения}';

    protected $description = 'Удалить тестовые данные за февраль 2026 (заявки, касса, документы)';

    private const FROM = '2026-02-01 00:00:00';

    private const TO = '2026-03-01 00:00:00';

    public function handle(): int
    {
        $orders = Order::query()
            ->with(['address.city', 'persons'])
            ->where('order_created_at', '>=', self::FROM)
            ->where('order_created_at', '<', self::TO)
            ->orderBy('order_id')
            ->get();

        $orderIds = $orders->pluck('order_id')->map(fn ($id) => (int) $id)->all();

        $cfmIds = DB::table('cfm_operations')
            ->where(function ($q) use ($orderIds) {
                $q->where('cfm_created_at', '>=', self::FROM)
                    ->where('cfm_created_at', '<', self::TO);
                if ($orderIds !== []) {
                    $q->orWhereIn('related_order_id', $orderIds);
                }
            })
            ->pluck('cfm_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->info('Заявок (созданы в фев 2026): '.$orders->count());
        if ($orders->isNotEmpty()) {
            $this->table(
                ['ID', 'Статус', 'Город', 'Создана', 'Описание'],
                $orders->map(fn (Order $o) => [
                    $o->order_id,
                    $o->order_status,
                    $o->address?->city?->city_name ?? '—',
                    $o->order_created_at?->format('Y-m-d H:i'),
                    mb_substr(str_replace(["\r", "\n"], ' ', (string) $o->order_adds), 0, 40) ?: '—',
                ])
            );
        }

        $cfmRows = DB::table('cfm_operations as o')
            ->join('cities as c', 'c.city_id', '=', 'o.city_id')
            ->join('cfm_categories as cat', 'cat.cfm_cat_id', '=', 'o.cfm_cat_id')
            ->whereIn('o.cfm_id', $cfmIds)
            ->select('o.cfm_id', 'c.city_name', 'cat.cfm_cat_name', 'o.amount_cfm', 'o.cfm_created_at', 'o.related_order_id')
            ->orderBy('o.cfm_id')
            ->get();

        $this->info('Операций кассы: '.count($cfmIds));
        if ($cfmRows->isNotEmpty()) {
            $this->table(
                ['cfm_id', 'Город', 'Статья', 'Сумма', 'Заказ'],
                $cfmRows->map(fn ($r) => [
                    $r->cfm_id,
                    $r->city_name,
                    $r->cfm_cat_name,
                    $r->amount_cfm,
                    $r->related_order_id ?? '—',
                ])
            );
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run — удаление не выполнялось.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить перечисленное безвозвратно?')) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $addressIds = $orders->pluck('address_id')->filter()->unique()->values()->all();
        $personIds = $orders->flatMap(fn (Order $o) => $o->persons->pluck('person_id'))->unique()->values()->all();

        DB::transaction(function () use ($orderIds, $cfmIds) {
            $cfmClass = DB::getPdo()->quote(CfmOperation::class);
            $orderClass = DB::getPdo()->quote(Order::class);

            if ($cfmIds !== []) {
                $cfmList = implode(',', $cfmIds);
                DB::unprepared("DELETE FROM documents WHERE documentable_type = {$cfmClass} AND documentable_id IN ({$cfmList})");
                if (Schema::hasTable('prom_payments')) {
                    DB::unprepared("UPDATE prom_payments SET cfm_operation_id = NULL WHERE cfm_operation_id IN ({$cfmList})");
                }
                DB::unprepared("DELETE FROM cfm_operations WHERE cfm_id IN ({$cfmList})");
            }

            if ($orderIds !== []) {
                $orderList = implode(',', $orderIds);
                DB::unprepared("DELETE FROM documents WHERE documentable_type = {$orderClass} AND documentable_id IN ({$orderList})");
                DB::unprepared('DELETE FROM order_persons WHERE order_id IN ('.$orderList.')');
                if (Schema::hasTable('order_city_views')) {
                    DB::unprepared('DELETE FROM order_city_views WHERE order_id IN ('.$orderList.')');
                }
                if (Schema::hasTable('order_activity_logs')) {
                    DB::unprepared('DELETE FROM order_activity_logs WHERE order_id IN ('.$orderList.')');
                }
                if (Schema::hasTable('partner_order_idempotency')) {
                    DB::unprepared('DELETE FROM partner_order_idempotency WHERE order_id IN ('.$orderList.')');
                }
                if (Schema::hasTable('calls')) {
                    DB::unprepared('UPDATE calls SET order_id = NULL WHERE order_id IN ('.$orderList.')');
                }
                DB::unprepared('DELETE FROM cfm_operations WHERE related_order_id IN ('.$orderList.')');
                DB::unprepared('DELETE FROM orders WHERE order_id IN ('.$orderList.')');
            }
        });

        $this->pruneOrphans($personIds, $addressIds);

        $this->info('Готово. Удалено заявок: '.count($orderIds).', операций кассы: '.count($cfmIds));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $personIds
     * @param  array<int, int>  $addressIds
     */
    private function pruneOrphans(array $personIds, array $addressIds): void
    {
        foreach ($addressIds as $addressId) {
            $used = DB::table('orders')->where('address_id', $addressId)->exists();
            if (! $used) {
                DB::table('addresses')->where('address_id', $addressId)->delete();
            }
        }

        foreach ($personIds as $personId) {
            $onOrder = DB::table('order_persons')->where('person_id', $personId)->exists();
            if (! $onOrder) {
                if (Schema::hasTable('person_phones')) {
                    DB::table('person_phones')->where('person_id', $personId)->delete();
                }
                DB::table('persons')->where('person_id', $personId)->delete();
            }
        }
    }
}
