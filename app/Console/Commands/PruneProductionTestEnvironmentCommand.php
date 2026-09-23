<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneProductionTestEnvironmentCommand extends Command
{
    protected $signature = 'app:prune-test-environment
                            {--dry-run : Только показать план}
                            {--force : Без подтверждения}';

    protected $description = 'Удалить тестовый контур с прода: Тестоград, тестовые учётки, адреса и клиенты';

    /** @var array<int, string> */
    private const PROTECTED_EMAILS = [
        'developer@lc.ru',
        'developer@leadcontrol.ru',
    ];

    /** @var array<int, string> */
    private const TEST_EMAIL_PREFIXES = [
        'testimir@',
        'testremont@',
        'testinstrument@',
        'testana@',
        'testmarina@',
        'testislav@',
        'testfilimon@',
        'testtechno@',
        'testofon@',
        'testograd_cli_orders@',
        'testsuperpart@',
        'teststarsh@',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $testogradId = City::query()->where('city_name', 'Тестоград')->value('city_id');
        $testUsers = $this->findTestUsers();
        $testUserIds = $testUsers->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $this->info('Тестоград city_id: '.($testogradId ?: '—'));
        $this->info('Тестовых учёток: '.$testUsers->count());
        if ($testUsers->isNotEmpty()) {
            $this->table(
                ['ID', 'Имя', 'Email'],
                $testUsers->map(fn (User $u) => [$u->user_id, $u->user_name, $u->email])
            );
        }

        if ($testogradId) {
            $addrCount = DB::table('addresses')->where('city_id', $testogradId)->count();
            $personCount = Person::query()
                ->whereHas('addresses', fn ($q) => $q->where('city_id', $testogradId))
                ->count();
            $this->info("Адресов в Тестограде: {$addrCount}, персон: {$personCount}");
        }

        $testSource = Source::query()
            ->where(function ($q) {
                $q->where('source_name', 'SUPERPART_DEFAULT_SOURCE_TEST')
                    ->orWhere('source_name', 'like', 'SUPERPART_DEFAULT_SOURCE_TEST%');
            })
            ->get(['source_id', 'source_name', 'is_active']);
        if ($testSource->isNotEmpty()) {
            $this->info('Тестовые источники:');
            foreach ($testSource as $s) {
                $cnt = Order::where('source_id', $s->source_id)->count();
                $this->line("  #{$s->source_id} {$s->source_name} (заказов: {$cnt})");
            }
        }

        if ($dryRun) {
            $this->info('Dry-run — изменения не применялись.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить тестовый контур безвозвратно?')) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $fallbackUserId = (int) (User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'developer'))
            ->value('user_id') ?? 1);

        if ($testUserIds !== []) {
            $idList = implode(',', $testUserIds);
            DB::unprepared('UPDATE orders SET master_id = NULL WHERE master_id IN ('.$idList.')');
            DB::unprepared('UPDATE orders SET order_created_by = '.$fallbackUserId.' WHERE order_created_by IN ('.$idList.')');
            DB::unprepared('UPDATE orders SET order_closed_by = NULL WHERE order_closed_by IN ('.$idList.')');
            DB::unprepared('UPDATE cfm_operations SET cfm_created_by = '.$fallbackUserId.' WHERE cfm_created_by IN ('.$idList.')');
            DB::unprepared('UPDATE cfm_operations SET cfm_closed_by = NULL WHERE cfm_closed_by IN ('.$idList.')');
            DB::unprepared('UPDATE complaints SET complaint_created_by = NULL WHERE complaint_created_by IN ('.$idList.')');
            DB::unprepared('UPDATE complaints SET complaint_closed_by = NULL WHERE complaint_closed_by IN ('.$idList.')');
            DB::unprepared('UPDATE reviews SET created_by = '.$fallbackUserId.' WHERE created_by IN ('.$idList.')');
            DB::unprepared('DELETE FROM master_schedules WHERE user_id IN ('.$idList.')');
            DB::unprepared('DELETE FROM interviews WHERE user_id IN ('.$idList.') OR manager_user_id IN ('.$idList.')');
            DB::unprepared('DELETE FROM order_activity_logs WHERE user_id IN ('.$idList.')');
            DB::unprepared('DELETE FROM user_roles WHERE user_id IN ('.$idList.')');
            DB::unprepared('DELETE FROM user_cities WHERE user_id IN ('.$idList.')');
            DB::unprepared('DELETE FROM sessions WHERE user_id IN ('.$idList.')');
            DB::unprepared('DELETE FROM users WHERE user_id IN ('.$idList.')');
            $this->info('Удалено учёток: '.count($testUserIds));
        }

        if ($testogradId) {
            $this->purgeTestograd((int) $testogradId);
        }

        foreach ($testSource as $source) {
            if (Order::where('source_id', $source->source_id)->exists()) {
                $source->update(['is_active' => false]);
                $this->line("Источник #{$source->source_id} деактивирован (есть заказы).");
            } else {
                DB::table('sources')->where('source_id', $source->source_id)->delete();
                $this->line("Источник #{$source->source_id} удалён.");
            }
        }

        $this->call('orders:prune-test-data', ['--force' => true]);

        $this->info('Готово.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function findTestUsers()
    {
        return User::query()
            ->get()
            ->filter(fn (User $user) => $this->isTestUser($user))
            ->values();
    }

    private function isTestUser(User $user): bool
    {
        $email = strtolower(trim((string) $user->email));
        if (in_array($email, self::PROTECTED_EMAILS, true)) {
            return false;
        }

        foreach (self::TEST_EMAIL_PREFIXES as $prefix) {
            if (str_starts_with($email, $prefix)) {
                return true;
            }
        }

        if (preg_match('/^test[a-z0-9._+-]*@lc\.ru$/', $email)) {
            return true;
        }

        $name = $user->user_name ?? '';
        $testNameMarkers = [
            'Тестимир', 'Тестович', 'Тестана', 'Тестислав', 'Технотест', 'Тестофон',
            'Тестоград CLI', 'TEST SuperPart', 'Тест старший дисп', 'Тестов тестов',
            'Филимон Тестов', 'Марина Тестова', 'Тестана Заявкина',
        ];
        foreach ($testNameMarkers as $marker) {
            if (str_contains($name, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function purgeTestograd(int $cityId): void
    {
        $personIds = Person::query()
            ->whereHas('addresses', fn ($q) => $q->where('city_id', $cityId))
            ->pluck('person_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $orderIds = DB::table('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->where('addresses.city_id', $cityId)
            ->pluck('orders.order_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($personIds !== []) {
            $fromPersons = DB::table('order_persons')
                ->whereIn('person_id', $personIds)
                ->pluck('order_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $orderIds = array_values(array_unique(array_merge($orderIds, $fromPersons)));
        }

        if ($orderIds !== []) {
            $this->deleteOrdersByIds($orderIds);
        }

        if ($personIds !== []) {
            $pList = implode(',', $personIds);
            DB::unprepared('DELETE FROM person_phones WHERE person_id IN ('.$pList.')');
            DB::unprepared('DELETE FROM persons WHERE person_id IN ('.$pList.')');
        }

        DB::unprepared('DELETE FROM addresses WHERE city_id = '.$cityId);
        DB::unprepared('DELETE FROM user_cities WHERE city_id = '.$cityId);
        DB::unprepared('DELETE FROM cfm_operations WHERE city_id = '.$cityId);
        DB::unprepared('UPDATE sources SET city_id = NULL WHERE city_id = '.$cityId);
        DB::unprepared('DELETE FROM cities WHERE city_id = '.$cityId);
        $this->info("Город Тестоград (id {$cityId}) и связанные клиенты удалены.");
    }

    /** @param  array<int, int>  $orderIds */
    private function deleteOrdersByIds(array $orderIds): void
    {
        if ($orderIds === []) {
            return;
        }

        $idList = implode(',', $orderIds);
        DB::unprepared('DELETE FROM documents WHERE documentable_type = '.DB::getPdo()->quote(Order::class).' AND documentable_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM order_persons WHERE order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM order_city_views WHERE order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM order_activity_logs WHERE order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM partner_order_idempotency WHERE order_id IN ('.$idList.')');
        DB::unprepared('UPDATE cfm_operations SET related_order_id = NULL WHERE related_order_id IN ('.$idList.')');
        DB::unprepared('DELETE FROM orders WHERE order_id IN ('.$idList.')');
    }
}
