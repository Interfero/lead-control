<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonPhone;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Тестовый контур: город Тестоград, сотрудники по ролям, 20 заказов (улица ТЕСТОВАЯ).
 *
 * Запуск: php artisan db:seed --class=TestogradSeeder
 * Нужны уже засеянные роли и источники (RoleSeeder, SourceSeeder или полный db:seed).
 *
 * Пароль у всех перечисленных учёток: см. константу TEST_PASSWORD (по умолчанию «password»).
 */
class TestogradSeeder extends Seeder
{
    private const TEST_PASSWORD = 'password';

    private const CITY_NAME = 'Тестоград';

    private const STREET = 'ТЕСТОВАЯ';

    public function run(): void
    {
        $roleCodes = [
            'master',
            'order_manager',
            'regional_director',
            'branch_head',
            'tech_director',
            'call_center',
        ];

        $roles = Role::query()->whereIn('role_code', $roleCodes)->get()->keyBy('role_code');
        foreach ($roleCodes as $code) {
            if (! $roles->has($code)) {
                $this->command->error("Роль «{$code}» не найдена. Сначала выполните RoleSeeder.");

                return;
            }
        }

        $source = Source::query()->where('is_active', true)->orderBy('source_id')->first();
        if (! $source) {
            $this->command->error('Нет активных источников заказов. Сначала выполните SourceSeeder.');

            return;
        }

        $city = City::updateOrCreate(
            ['city_name' => self::CITY_NAME],
            [
                'city_type' => 'city',
                'city_timezone' => 'Europe/Moscow',
                'is_active' => true,
                'parent_city_id' => null,
            ]
        );

        $employees = [
            ['Тестимир Починяев', 'testimir@lc.ru', 'master'],
            ['Ремонтослав Тестов', 'testremont@lc.ru', 'master'],
            ['Инструмент Тестович', 'testinstrument@lc.ru', 'master'],
            ['Тестана Заявкина', 'testana@lc.ru', 'order_manager'],
            ['Марина Тестова', 'testmarina@lc.ru', 'order_manager'],
            ['Тестислав Регионов', 'testislav@lc.ru', 'regional_director'],
            ['Филимон Тестов', 'testfilimon@lc.ru', 'branch_head'],
            ['Технотест Системов', 'testtechno@lc.ru', 'tech_director'],
            ['Тестофон Звонков', 'testofon@lc.ru', 'call_center'],
        ];

        $usersByCode = [];
        foreach ($employees as [$name, $email, $roleCode]) {
            $user = $this->ensureEmployee($name, $email, $roles[$roleCode], $city);
            $usersByCode[$roleCode] ??= [];
            $usersByCode[$roleCode][] = $user;
        }

        $masters = $usersByCode['master'];
        $managers = $usersByCode['order_manager'];
        $branchHead = $usersByCode['branch_head'][0];

        $equipmentCycle = array_keys(array_slice(Order::EQUIPMENT_TYPES, 0, 20, true));
        $statuses = [
            'pending', 'pending', 'pending',
            'callback', 'callback',
            'in_progress', 'in_progress', 'in_progress', 'in_progress',
            'in_progress_sd', 'in_progress_sd',
            'completed', 'completed', 'completed', 'completed', 'completed', 'completed',
            'cancelled_cc',
            'cancelled_city', 'cancelled_city',
        ];

        $closedStatuses = ['completed', 'cancelled_cc', 'cancelled_city'];

        for ($i = 1; $i <= 20; $i++) {
            $person = Person::query()->create([
                'person_name' => "Клиент тестовый №{$i}",
                'person_age' => 25 + ($i % 40),
            ]);

            PersonPhone::query()->create([
                'person_id' => $person->person_id,
                'phone_number' => str_pad((string) (9_001_000_000 + $i), 10, '0', STR_PAD_LEFT),
            ]);

            $address = Address::query()->create([
                'person_id' => $person->person_id,
                'city_id' => $city->city_id,
                'street' => self::STREET,
                'house' => (string) $i,
                'flat' => (string) ($i * 10 % 99 + 1),
                'address_adds' => null,
            ]);

            $equipment = $equipmentCycle[($i - 1) % count($equipmentCycle)];
            $orderCore = Order::getOrderCoreByEquipment($equipment);
            $status = $statuses[$i - 1];

            $creator = $managers[($i - 1) % count($managers)];
            $master = in_array($status, ['in_progress', 'in_progress_sd', 'completed'], true)
                ? $masters[($i - 1) % count($masters)]->user_id
                : null;

            $order = new Order([
                'datetime_order' => now()->addDays($i)->setHour(10 + ($i % 6))->setMinute(0),
                'address_id' => $address->address_id,
                'order_status' => $status,
                'order_type' => $i % 5 === 0 ? 'repeat' : ($i % 7 === 0 ? 'warranty' : 'new'),
                'order_core' => $orderCore,
                'equipment_type' => $equipment,
                'amount_paid' => in_array($status, $closedStatuses, true) ? 1500 * $i : 0,
                'amount_comp' => in_array($status, ['completed'], true) ? 500 * ($i % 5) : 0,
                'master_id' => $master,
                'source_id' => $source->source_id,
                'order_adds' => "Тестовый заказ №{$i}: диагностика, тип «{$equipment}».",
                'shift_adds' => null,
                'city_adds' => null,
            ]);

            $order->order_created_by = $creator->user_id;
            $order->order_created_at = now()->subDays(21 - $i);

            if (in_array($status, $closedStatuses, true)) {
                $order->order_closed_at = now()->subDays(max(1, 20 - $i));
                $order->order_closed_by = $branchHead->user_id;
            }

            $order->save();
            $order->persons()->sync([$person->person_id]);
        }

        $this->command->info('Тестоград: город, '.count($employees).' сотрудников, 20 заказов (улица '.self::STREET.').');
        $this->command->warn('Пароль тестовых учёток: '.self::TEST_PASSWORD);

        $this->call(PruneCitiesExceptTestogradSeeder::class);
    }

    private function ensureEmployee(string $name, string $email, Role $role, City $city): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'user_name' => $name,
                'password' => Hash::make(self::TEST_PASSWORD),
                'is_active' => true,
                'user_hired_at' => now()->subMonths(2),
            ]
        );

        $user->roles()->sync([$role->role_id]);
        $user->cities()->sync([$city->city_id]);

        return $user;
    }
}
