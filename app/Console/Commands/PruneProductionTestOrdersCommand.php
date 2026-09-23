<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneProductionTestOrdersCommand extends Command
{
    protected $signature = 'orders:prune-test-data
                            {--dry-run : Только показать, что будет удалено}
                            {--include-superpart-test : Удалить заказы с тестовым источником SUPERPART}
                            {--force : Без подтверждения}';

    protected $description = 'Удалить тестовые заявки с прода (Тестоград, улица ТЕСТОВАЯ, опционально SUPERPART test)';

    public function handle(): int
    {
        $testogradId = City::query()->where('city_name', 'Тестоград')->value('city_id');

        $query = Order::query()
            ->with(['address.city', 'persons'])
            ->where(function ($q) use ($testogradId) {
                if ($testogradId) {
                    $q->whereHas('address', fn ($a) => $a->where('city_id', $testogradId));
                }
                $q->orWhereHas('address', fn ($a) => $a->where('street', 'like', '%ТЕСТОВАЯ%'));
                if ($this->option('include-superpart-test')) {
                    $q->orWhere('source_id', 6);
                }
            });

        $orders = $query->orderBy('order_id')->get();

        if ($orders->isEmpty()) {
            $this->info('Тестовых заявок не найдено.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Статус', 'Город', 'Улица', 'Клиент'],
            $orders->map(fn (Order $o) => [
                $o->order_id,
                $o->order_status,
                $o->address?->city?->city_name ?? '—',
                $o->address?->street ?? '—',
                $o->persons->first()?->person_name ?? '—',
            ])
        );

        $this->warn('Найдено заявок: '.$orders->count());

        if ($this->option('dry-run')) {
            $this->info('Режим dry-run — удаление не выполнялось.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить эти заявки безвозвратно?')) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $orderIds = $orders->pluck('order_id')->map(fn ($id) => (int) $id)->all();
        $idList = implode(',', $orderIds);

        DB::unprepared('DELETE FROM documents WHERE documentable_type = '.DB::getPdo()->quote(Order::class).' AND documentable_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM order_persons WHERE order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM order_city_views WHERE order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM order_activity_logs WHERE order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM partner_order_idempotency WHERE order_id IN ('.$idList.')');
        DB::unprepared('UPDATE cfm_operations SET related_order_id = NULL WHERE related_order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM orders WHERE order_id IN ('.$idList.')');

        $deleted = count($orderIds);

        $this->info("Удалено заявок: {$deleted}");

        return self::SUCCESS;
    }
}
