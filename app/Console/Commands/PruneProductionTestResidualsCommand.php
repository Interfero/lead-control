<?php

namespace App\Console\Commands;

use App\Models\CfmOperation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneProductionTestResidualsCommand extends Command
{
    protected $signature = 'app:prune-test-residuals
                            {--dry-run : Только показать план}
                            {--force : Без подтверждения}';

    protected $description = 'Удалить оставшиеся тестовые заявки/кассу/учётки (не трогает «Не оформлена» с нормальным описанием)';

    /** @var list<int> */
    private const PROTECTED_USER_IDS = [1, 110, 149];

    public function handle(): int
    {
        $genDirIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'general_director'))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $orders = $this->findTestOrders($genDirIds);
        $orderIds = $orders->pluck('order_id')->map(fn ($id) => (int) $id)->all();

        $cfmIds = $this->findTestCfmIds($orderIds);
        $testUsers = $this->findDeletableTestUsers();

        if ($orders->isNotEmpty()) {
            $this->table(
                ['ID', 'Статус', 'Город', 'Клиент', 'Сумма', 'Автор'],
                $orders->map(fn (Order $o) => [
                    $o->order_id,
                    $o->order_status,
                    $o->address?->city?->city_name ?? '—',
                    $o->persons->first()?->person_name ?? '—',
                    $o->amount_paid,
                    $o->creator?->user_name ?? '—',
                ])
            );
        } else {
            $this->info('Тестовых заявок не найдено.');
        }

        $this->info('Операций кассы к удалению: '.count($cfmIds));
        if ($cfmIds !== []) {
            $rows = DB::table('cfm_operations as o')
                ->join('cities as c', 'c.city_id', '=', 'o.city_id')
                ->join('cfm_categories as cat', 'cat.cfm_cat_id', '=', 'o.cfm_cat_id')
                ->whereIn('o.cfm_id', $cfmIds)
                ->select('o.cfm_id', 'c.city_name', 'cat.cfm_cat_name', 'o.amount_cfm', 'o.related_order_id')
                ->orderBy('o.cfm_id')
                ->get();
            $this->table(['cfm_id', 'Город', 'Статья', 'Сумма', 'Заказ'], $rows->map(fn ($r) => [
                $r->cfm_id, $r->city_name, $r->cfm_cat_name, $r->amount_cfm, $r->related_order_id ?? '—',
            ]));
        }

        if ($testUsers->isNotEmpty()) {
            $this->info('Учёток к удалению: '.$testUsers->count());
            foreach ($testUsers as $u) {
                $this->line("  #{$u->user_id} {$u->user_name} <{$u->email}>");
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run — изменения не применялись.');

            return self::SUCCESS;
        }

        if ($orders->isEmpty() && $cfmIds === [] && $testUsers->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить перечисленное?')) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $addressIds = $orders->pluck('address_id')->filter()->unique()->values()->all();
        $personIds = $orders->flatMap(fn (Order $o) => $o->persons->pluck('person_id'))->unique()->values()->all();

        DB::transaction(function () use ($orderIds, $cfmIds, $testUsers) {
            $this->deleteCfmByIds($cfmIds);
            $this->deleteOrdersByIds($orderIds);
            $this->deleteTestUsers($testUsers);
        });

        $this->pruneOrphans($personIds, $addressIds);

        $this->info('Готово.');

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $genDirIds
     * @return \Illuminate\Support\Collection<int, Order>
     */
    private function findTestOrders(array $genDirIds)
    {
        return Order::query()
            ->with(['address.city', 'persons', 'creator', 'source'])
            ->where(function ($q) use ($genDirIds) {
                $q->whereHas('persons', function ($p) {
                    $p->where('person_name', 'like', 'заказ%')
                        ->orWhere('person_name', 'like', 'Заказ%')
                        ->orWhere('person_name', 'like', 'вид работ%')
                        ->orWhere('person_name', 'like', 'оренбург клиент%')
                        ->orWhere('person_name', 'like', '%тест%')
                        ->orWhere('person_name', 'like', '%Тест%')
                        ->orWhere('person_name', 'like', 'увед%')
                        ->orWhere('person_name', 'like', 'katalon%')
                        ->orWhere('person_name', 'like', 'Katalon%')
                        ->orWhere('person_name', 'like', 'не назначено%')
                        ->orWhere('person_name', 'like', 'КЦ_%');
                })
                    ->orWhereHas('address', fn ($a) => $a->where('street', 'like', '%ТЕСТ%')
                        ->orWhere('street', 'like', '%??%'))
                    ->orWhere(function ($g) use ($genDirIds) {
                        if ($genDirIds === []) {
                            return;
                        }
                        $g->whereIn('order_created_by', $genDirIds)
                            ->where('amount_paid', 0)
                            ->where('amount_comp', 0)
                            ->where('order_status', 'like', 'cancelled%')
                            ->whereHas('source', fn ($s) => $s->where('source_name', 'like', '%Партнер%'));
                    })
                    ->orWhere(function ($g) use ($genDirIds) {
                        if ($genDirIds === []) {
                            return;
                        }
                        $g->whereIn('order_created_by', $genDirIds)
                            ->where('amount_paid', 0)
                            ->where('amount_comp', 0)
                            ->where('order_id', '<', 100);
                    });
            })
            ->where('order_status', '!=', 'not_processed')
            ->orderBy('order_id')
            ->get();
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<int>
     */
    private function findTestCfmIds(array $orderIds): array
    {
        $ids = DB::table('cfm_operations as o')
            ->join('cfm_categories as cat', 'cat.cfm_cat_id', '=', 'o.cfm_cat_id')
            ->where(function ($q) use ($orderIds) {
                if ($orderIds !== []) {
                    $q->whereIn('o.related_order_id', $orderIds);
                }
                $q->orWhere(function ($inner) {
                    $inner->whereNull('o.related_order_id')
                        ->where('cat.cfm_cat_name', 'Зарплата промоутеров')
                        ->where('o.amount_cfm', '<=', 100);
                })
                    ->orWhere(function ($inner) {
                        $inner->whereNull('o.related_order_id')
                            ->where('cat.cfm_cat_name', 'Налоги')
                            ->where('o.amount_cfm', '<=', 5000)
                            ->where(function ($a) {
                                $a->whereNull('o.cfm_adds')
                                    ->orWhereRaw('CHAR_LENGTH(TRIM(o.cfm_adds)) <= 6');
                            });
                    });
            })
            ->pluck('o.cfm_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function findDeletableTestUsers()
    {
        return User::query()
            ->whereNotIn('user_id', self::PROTECTED_USER_IDS)
            ->where(function ($q) {
                $q->where('user_name', 'like', 'Тестович%')
                    ->orWhere('user_name', 'like', 'Тест %')
                    ->orWhere('email', 'like', 'dfsdfs%')
                    ->orWhere(function ($q2) {
                        $q2->where('email', 'like', 'test%@%.%')
                            ->where('is_active', false);
                    });
            })
            ->get();
    }

    /** @param  list<int>  $cfmIds */
    private function deleteCfmByIds(array $cfmIds): void
    {
        if ($cfmIds === []) {
            return;
        }
        $list = implode(',', $cfmIds);
        $cfmClass = DB::getPdo()->quote(CfmOperation::class);
        DB::unprepared("DELETE FROM documents WHERE documentable_type = {$cfmClass} AND documentable_id IN ({$list})");
        if (Schema::hasTable('prom_payments')) {
            DB::unprepared("UPDATE prom_payments SET cfm_operation_id = NULL WHERE cfm_operation_id IN ({$list})");
        }
        DB::unprepared("DELETE FROM cfm_operations WHERE cfm_id IN ({$list})");
    }

    /** @param  list<int>  $orderIds */
    private function deleteOrdersByIds(array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }
        $list = implode(',', $orderIds);
        $orderClass = DB::getPdo()->quote(Order::class);
        DB::unprepared("DELETE FROM documents WHERE documentable_type = {$orderClass} AND documentable_id IN ({$list})");
        DB::unprepared('DELETE FROM order_persons WHERE order_id IN ('.$list.')');
        if (Schema::hasTable('order_city_views')) {
            DB::unprepared('DELETE FROM order_city_views WHERE order_id IN ('.$list.')');
        }
        if (Schema::hasTable('order_activity_logs')) {
            DB::unprepared('DELETE FROM order_activity_logs WHERE order_id IN ('.$list.')');
        }
        if (Schema::hasTable('partner_order_idempotency')) {
            DB::unprepared('DELETE FROM partner_order_idempotency WHERE order_id IN ('.$list.')');
        }
        if (Schema::hasTable('calls')) {
            DB::unprepared('UPDATE calls SET order_id = NULL WHERE order_id IN ('.$list.')');
        }
        DB::unprepared('DELETE FROM cfm_operations WHERE related_order_id IN ('.$list.')');
        DB::unprepared('DELETE FROM orders WHERE order_id IN ('.$list.')');
    }

    /** @param  \Illuminate\Support\Collection<int, User>  $users */
    private function deleteTestUsers($users): void
    {
        foreach ($users as $user) {
            $uid = (int) $user->user_id;
            DB::unprepared('DELETE FROM master_schedules WHERE user_id = '.$uid);
            DB::unprepared('UPDATE orders SET master_id = NULL WHERE master_id = '.$uid);
            DB::unprepared('UPDATE orders SET order_created_by = 1 WHERE order_created_by = '.$uid);
            DB::unprepared('UPDATE orders SET order_closed_by = NULL WHERE order_closed_by = '.$uid);
            DB::unprepared('UPDATE cfm_operations SET cfm_created_by = 1 WHERE cfm_created_by = '.$uid);
            DB::unprepared('UPDATE cfm_operations SET cfm_closed_by = NULL WHERE cfm_closed_by = '.$uid);
            DB::unprepared('DELETE FROM user_roles WHERE user_id = '.$uid);
            DB::unprepared('DELETE FROM user_cities WHERE user_id = '.$uid);
            DB::unprepared('DELETE FROM sessions WHERE user_id = '.$uid);
            DB::unprepared('DELETE FROM users WHERE user_id = '.$uid);
        }
    }

    /**
     * @param  list<int>  $personIds
     * @param  list<int>  $addressIds
     */
    private function pruneOrphans(array $personIds, array $addressIds): void
    {
        foreach ($addressIds as $addressId) {
            if (! DB::table('orders')->where('address_id', $addressId)->exists()) {
                DB::table('addresses')->where('address_id', $addressId)->delete();
            }
        }
        foreach ($personIds as $personId) {
            if (DB::table('order_persons')->where('person_id', $personId)->exists()) {
                continue;
            }
            if (Schema::hasTable('person_phones')) {
                DB::table('person_phones')->where('person_id', $personId)->delete();
            }
            DB::table('persons')->where('person_id', $personId)->delete();
        }
    }
}
