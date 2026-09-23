<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Одноразовое исправление после TestogradSeeder: у «менеджеров по заказам» нет доступа к маршрутам заказов.
 * Назначает роль senior_manager (менеджер филиала) учёткам Тестана Заявкина и Марина Тестова.
 *
 * Запуск (на сервере или локально, после выгрузки файла):
 *   php artisan db:seed --class=TestogradManagersSeniorManagerSeeder
 *
 * Повторный запуск безопасен: если роль уже senior_manager, изменений не будет.
 */
class TestogradManagersSeniorManagerSeeder extends Seeder
{
    private const EMAILS = [
        'testana@lc.ru',
        'testmarina@lc.ru',
    ];

    public function run(): void
    {
        $from = Role::query()->where('role_code', 'order_manager')->first();
        $to = Role::query()->where('role_code', 'senior_manager')->first();

        if (! $from || ! $to) {
            $this->command?->error('Роли order_manager или senior_manager не найдены в справочнике.');

            return;
        }

        foreach (self::EMAILS as $email) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                $this->command?->warn("Пользователь {$email} не найден — пропуск.");

                continue;
            }

            if ($user->roles()->where('role_code', 'order_manager')->exists()) {
                $user->roles()->detach($from->role_id);
                if (! $user->roles()->where('role_code', 'senior_manager')->exists()) {
                    $user->roles()->attach($to->role_id);
                }
                $this->command?->info("Обновлена роль: {$email} → senior_manager.");
            } elseif ($user->roles()->where('role_code', 'senior_manager')->exists()) {
                $this->command?->info("Уже senior_manager: {$email} — без изменений.");
            } else {
                $this->command?->warn("{$email}: нет роли order_manager — пропуск (проверьте учётку вручную).");
            }
        }
    }
}
