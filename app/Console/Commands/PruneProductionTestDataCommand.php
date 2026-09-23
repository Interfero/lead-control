<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneProductionTestDataCommand extends Command
{
    protected $signature = 'db:prune-production-test-data
                            {--dry-run : Только показать план}
                            {--force : Без подтверждения}';

    protected $description = 'Очистить тестовые данные на проде: заявки, учётки, город Тестоград, лишние спутники';

    /** @var list<string> */
    private const TEST_EMAILS = [
        'test12345@level.ion',
        'cctest@level.ion',
        'grandtester@lc.ru',
        'testimir@level.ion',
        'testremont@level.ion',
        'testinstrument@lc.ru',
        'testana@level.ion',
        'testmarina@level.ion',
        'testislav@level.ion',
        'testfilimon@level.ion',
        'testtechno@level.ion',
        'testofon@level.ion',
        'testograd_cli_orders@lc.ru',
        'testSuperPart@lc.ru',
        'Gdhdjdhhshss@lc.ru',
        'teststarsh@lc.ru',
    ];

    /** @var list<int> */
    private const PROTECTED_USER_IDS = [1, 110];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== 1. Спутники: только Дзержинск → Н. Новгород ===');
        $this->fixSatelliteCities($dryRun);

        $this->info('=== 2. Тестовые заявки ===');
        $this->call('orders:prune-test-data', array_filter([
            '--dry-run' => $dryRun,
            '--force' => ! $dryRun && $this->option('force'),
            '--include-superpart-test' => true,
        ]));

        $testUsers = $this->findTestUsers();
        $this->info('=== 3. Тестовые учётки ('.$testUsers->count().') ===');
        foreach ($testUsers as $u) {
            $this->line("  #{$u->user_id} {$u->user_name} <{$u->email}>");
        }

        $testograd = City::query()->where('city_name', 'Тестоград')->first();
        if ($testograd) {
            $this->info('=== 4. Город Тестоград (id='.$testograd->city_id.') ===');
        }

        if ($dryRun) {
            $this->info('Dry-run: учётки и город не удалялись.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить тестовые учётки и город Тестоград?')) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $this->deleteTestUsers($testUsers);
        if ($testograd) {
            $this->deleteTestogradCity((int) $testograd->city_id);
        }

        $this->info('Готово.');

        return self::SUCCESS;
    }

    private function fixSatelliteCities(bool $dryRun): void
    {
        $nnId = City::query()->where('city_name', 'Нижний Новгород')->value('city_id');
        if (! $nnId) {
            $this->warn('Нижний Новгород не найден в справочнике.');

            return;
        }

        // Не сбрасываем parent у других кластеров — связи спутников задаются командой cities:sync-org-map.
        $dzerzhinsk = City::query()
            ->where(function ($q) {
                $q->where('city_name', 'Дзержинск')
                    ->orWhere('city_name', 'like', 'Дзержинск (%');
            })
            ->first();
        if ($dzerzhinsk && (int) $dzerzhinsk->parent_city_id !== (int) $nnId) {
            $this->line("  Дзержинск → parent Н. Новгород (id={$nnId})");
            if (! $dryRun) {
                $dzerzhinsk->update(['parent_city_id' => $nnId]);
            }
        }
    }

    private function findTestUsers()
    {
        return User::query()
            ->with('roles')
            ->whereNotIn('user_id', self::PROTECTED_USER_IDS)
            ->where(function ($q) {
                $q->whereIn('email', self::TEST_EMAILS)
                    ->orWhere('email', 'like', 'test%')
                    ->orWhere('email', 'like', 'TEST%')
                    ->orWhere('user_name', 'like', 'Тест%')
                    ->orWhere('user_name', 'like', 'TEST %')
                    ->orWhere('user_name', 'like', '% Тестов')
                    ->orWhere('user_name', 'like', '% Тестова')
                    ->orWhere('user_name', 'like', 'Тестимир%')
                    ->orWhere('user_name', 'like', 'Тестислав%')
                    ->orWhere('user_name', 'like', 'Технотест%')
                    ->orWhere('user_name', 'like', 'Тестофон%')
                    ->orWhere('user_name', 'like', 'Тестана%')
                    ->orWhere('user_name', 'like', 'Тестоград%');
            })
            ->orderBy('user_id')
            ->get();
    }

    private function deleteTestUsers($users): void
    {
        foreach ($users as $user) {
            $uid = (int) $user->user_id;
            DB::unprepared('DELETE FROM master_schedules WHERE user_id = '.$uid);
            DB::unprepared('UPDATE orders SET master_id = NULL WHERE master_id = '.$uid);
            DB::unprepared('UPDATE orders SET order_created_by = 1 WHERE order_created_by = '.$uid);
            DB::unprepared('UPDATE orders SET order_closed_by = 1 WHERE order_closed_by = '.$uid);
            DB::unprepared('DELETE FROM user_cities WHERE user_id = '.$uid);
            DB::unprepared('DELETE FROM user_roles WHERE user_id = '.$uid);
            DB::unprepared('DELETE FROM sessions WHERE user_id = '.$uid);
            DB::unprepared('DELETE FROM users WHERE user_id = '.$uid);
            $this->line("  Удалён user #{$uid} ({$user->user_name})");
        }
    }

    private function deleteTestogradCity(int $cityId): void
    {
        $personIds = DB::table('addresses')
            ->where('city_id', $cityId)
            ->whereNotNull('person_id')
            ->distinct()
            ->pluck('person_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($personIds as $personId) {
            DB::unprepared('DELETE FROM person_phones WHERE person_id = '.$personId);
            DB::unprepared('DELETE FROM addresses WHERE person_id = '.$personId);
            DB::unprepared('DELETE FROM persons WHERE person_id = '.$personId);
        }

        $addressIds = DB::table('addresses')->where('city_id', $cityId)->pluck('address_id')->all();
        if ($addressIds !== []) {
            $addrList = implode(',', array_map('intval', $addressIds));
            $orderIds = DB::table('orders')->whereIn('address_id', $addressIds)->pluck('order_id')->all();
            if ($orderIds !== []) {
                $idList = implode(',', array_map('intval', $orderIds));
                DB::unprepared('DELETE FROM order_persons WHERE order_id IN ('.$idList.')');
                DB::unprepared('DELETE FROM orders WHERE order_id IN ('.$idList.')');
            }
            DB::unprepared('DELETE FROM addresses WHERE address_id IN ('.$addrList.')');
        }

        DB::unprepared('DELETE FROM user_cities WHERE city_id = '.$cityId);
        DB::unprepared('DELETE FROM cities WHERE city_id = '.$cityId);
        $this->line('  Удалён город Тестоград');
    }
}
