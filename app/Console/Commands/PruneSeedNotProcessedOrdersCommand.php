<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneSeedNotProcessedOrdersCommand extends Command
{
    protected $signature = 'orders:prune-seed-not-processed
                            {--dry-run : Только показать список}
                            {--force : Без подтверждения}
                            {--also-orphans : Удалить клиентов/адреса, оставшиеся без заказов после чистки}';

    protected $description = 'Удалить тестовые заявки «Не оформлена» (seed/уведомления/явные тесты), без боевых заявок с нормальным описанием';

    public function handle(): int
    {
        $orders = $this->candidateQuery()->with(['address.city', 'persons', 'creator', 'cfmOperations'])->orderBy('order_id')->get();

        if ($orders->isEmpty()) {
            $this->info('Кандидатов на удаление нет.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Город', 'Клиент', 'Автор', 'Описание', 'CFM'],
            $orders->map(fn (Order $o) => [
                $o->order_id,
                $o->address?->city?->city_name ?? '—',
                $o->persons->first()?->person_name ?? '—',
                $o->creator?->user_name ?? '—',
                mb_substr(str_replace(["\r", "\n"], ' ', (string) $o->order_adds), 0, 48) ?: '—',
                $o->cfmOperations->count(),
            ])
        );

        $this->warn('Найдено заявок: '.$orders->count());

        if ($this->option('dry-run')) {
            $this->info('Dry-run — удаление не выполнялось.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить эти заявки безвозвратно?')) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $orderIds = $orders->pluck('order_id')->map(fn ($id) => (int) $id)->all();
        $personIds = $orders->flatMap(fn (Order $o) => $o->persons->pluck('person_id'))->unique()->values()->all();
        $addressIds = $orders->pluck('address_id')->filter()->unique()->values()->all();

        DB::transaction(function () use ($orderIds) {
            $idList = implode(',', $orderIds);
            $orderClass = DB::getPdo()->quote(Order::class);

            DB::unprepared("DELETE FROM documents WHERE documentable_type = {$orderClass} AND documentable_id IN ({$idList})");
            DB::unprepared("DELETE FROM order_persons WHERE order_id IN ({$idList})");
            if ($this->tableExists('order_city_views')) {
                DB::unprepared("DELETE FROM order_city_views WHERE order_id IN ({$idList})");
            }
            if ($this->tableExists('order_activity_logs')) {
                DB::unprepared("DELETE FROM order_activity_logs WHERE order_id IN ({$idList})");
            }
            if ($this->tableExists('partner_order_idempotency')) {
                DB::unprepared("DELETE FROM partner_order_idempotency WHERE order_id IN ({$idList})");
            }
            DB::unprepared("DELETE FROM cfm_operations WHERE related_order_id IN ({$idList})");
            DB::unprepared("DELETE FROM orders WHERE order_id IN ({$idList})");
        });

        $this->info('Удалено заявок: '.count($orderIds));

        if ($this->option('also-orphans')) {
            $this->pruneOrphans($personIds, $addressIds);
        }

        return self::SUCCESS;
    }

    private function candidateQuery()
    {
        $genDirIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'general_director'))
            ->pluck('user_id')
            ->all();

        return Order::query()
            ->where('order_status', 'not_processed')
            ->where(function ($q) use ($genDirIds) {
                // Явные тесты по тексту / клиенту / улице (даже с ненулевой суммой)
                $q->where(function ($explicit) {
                    $explicit->where('order_adds', 'like', '%тест%')
                        ->orWhere('order_adds', 'like', '%ТЕСТ%')
                        ->orWhere('order_adds', 'like', '%test%')
                        ->orWhereHas('persons', function ($p) {
                            $p->where('person_name', 'like', '%тест%')
                                ->orWhere('person_name', 'like', '%Тест%')
                                ->orWhere('person_name', 'like', '%TEST%')
                                ->orWhere('person_name', 'like', 'увед%')
                                ->orWhere('person_name', 'like', 'уведы%')
                                ->orWhere('person_name', 'like', 'заказ %')
                                ->orWhere('person_name', 'like', 'Заказ %')
                                ->orWhere('person_name', 'like', 'заказа %')
                                ->orWhere('person_name', 'like', 'katalon%')
                                ->orWhere('person_name', 'like', 'не назначено%')
                                ->orWhere('person_name', 'like', 'вид работ%')
                                ->orWhere('person_name', 'like', 'КЦ_%')
                                ->orWhere('person_name', 'like', 'кл %')
                                ->orWhere('person_name', 'like', 'проверка%')
                                ->orWhere('person_name', 'like', 'долгопрк%');
                        })
                        ->orWhereHas('address', function ($a) {
                            $a->where('street', 'like', '%??%')
                                ->orWhere('street', 'like', '%хз%')
                                ->orWhere('street', 'like', '%ТЕСТ%');
                        });
                });

                // Seed от гендиректора с нулевыми суммами
                if ($genDirIds !== []) {
                    $q->orWhere(function ($seed) use ($genDirIds) {
                        $seed->whereIn('order_created_by', $genDirIds)
                            ->where('amount_paid', 0)
                            ->where('amount_comp', 0)
                            ->where(function ($inner) {
                                $inner->whereNull('order_adds')
                                    ->orWhereRaw('CHAR_LENGTH(TRIM(order_adds)) <= 20')
                                    ->orWhereHas('persons', function ($p) {
                                        $p->where('person_name', 'like', 'увед%')
                                            ->orWhere('person_name', 'like', 'заказ%')
                                            ->orWhere('person_name', 'like', 'Заказ%')
                                            ->orWhere('person_name', 'like', '%тест%')
                                            ->orWhere('person_name', 'like', 'katalon%');
                                    });
                            });
                    });
                }
            });
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    /**
     * @param  list<int>  $personIds
     * @param  list<int>  $addressIds
     */
    private function pruneOrphans(array $personIds, array $addressIds): void
    {
        $orphanedPersons = 0;
        foreach ($personIds as $personId) {
            $stillUsed = DB::table('order_persons')->where('person_id', $personId)->exists();
            if ($stillUsed) {
                continue;
            }
            $addrIds = DB::table('addresses')->where('person_id', $personId)->pluck('address_id')->all();
            foreach ($addrIds as $aid) {
                $addrUsedOnOrder = DB::table('orders')->where('address_id', $aid)->exists();
                if (! $addrUsedOnOrder) {
                    DB::table('addresses')->where('address_id', $aid)->delete();
                } else {
                    DB::table('addresses')->where('address_id', $aid)->update(['person_id' => null]);
                }
            }
            DB::table('person_phones')->where('person_id', $personId)->delete();
            DB::table('persons')->where('person_id', $personId)->delete();
            $orphanedPersons++;
        }

        $orphanedAddresses = 0;
        foreach ($addressIds as $addressId) {
            $addrUsed = DB::table('orders')->where('address_id', $addressId)->exists();
            if (! $addrUsed) {
                DB::table('addresses')->where('address_id', $addressId)->delete();
                $orphanedAddresses++;
            }
        }

        $this->info("Удалено сирот: клиентов={$orphanedPersons}, адресов≈{$orphanedAddresses}");
    }
}
