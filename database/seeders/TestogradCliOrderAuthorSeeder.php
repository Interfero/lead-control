<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Учётка для авторства заявок из команды `order:create-demo` (Тестоград: city_id=49, user_id=131).
 *
 * Идемпотентно: если пользователь с user_id=131 уже есть — только привязка к городу 49 (без смены ролей).
 * Если нет — создаётся пользователь с фиксированным id, роль order_manager, город 49.
 *
 * Запуск: php artisan db:seed --class=TestogradCliOrderAuthorSeeder
 */
class TestogradCliOrderAuthorSeeder extends Seeder
{
    public const CITY_ID_TESTOGRAD = 49;

    public const USER_ID_CLI_AUTHOR = 131;

    private const EMAIL = 'testograd_cli_orders@lc.ru';

    private const TEST_PASSWORD = 'password';

    public function run(): void
    {
        $city = City::find(self::CITY_ID_TESTOGRAD);
        if (! $city) {
            $this->command?->error('Город city_id='.self::CITY_ID_TESTOGRAD.' не найден.');

            return;
        }

        $role = Role::where('role_code', 'order_manager')->first();
        if (! $role) {
            $this->command?->error('Роль order_manager не найдена. Выполните RoleSeeder.');

            return;
        }

        $existing = User::find(self::USER_ID_CLI_AUTHOR);
        if ($existing) {
            $existing->cities()->syncWithoutDetaching([self::CITY_ID_TESTOGRAD]);
            $this->command?->info('Пользователь user_id='.self::USER_ID_CLI_AUTHOR.' уже существует; город '.self::CITY_ID_TESTOGRAD.' привязан при необходимости.');

            return;
        }

        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->error('Email '.self::EMAIL.' уже занят другим user_id. Удалите конфликт вручную или смените EMAIL в сидере.');

            return;
        }

        $user = new User([
            'user_name' => 'Тестоград CLI (автор заявок)',
            'email' => self::EMAIL,
            'password' => Hash::make(self::TEST_PASSWORD),
            'is_active' => true,
            'user_hired_at' => now()->subMonth(),
        ]);
        $user->user_id = self::USER_ID_CLI_AUTHOR;
        $user->save();

        $user->roles()->sync([$role->role_id]);
        $user->cities()->sync([self::CITY_ID_TESTOGRAD]);

        $this->command?->info('Создан пользователь user_id='.self::USER_ID_CLI_AUTHOR.' ('.self::EMAIL.'), пароль: '.self::TEST_PASSWORD);
    }
}
