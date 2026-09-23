<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDatabaseForRussia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clean-for-russia {--force : Force execution without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистка базы данных для старта проекта в России. Удаляет все данные кроме пользователя-разработчика и города "Управляющая компания", создает город "Москва".';

    /**
     * Получить тип базы данных
     */
    private function getDatabaseDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Отключить проверку foreign keys
     */
    private function disableForeignKeyChecks(): void
    {
        $driver = $this->getDatabaseDriver();
        
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
        // Для других БД проверка foreign keys не отключается
    }

    /**
     * Включить проверку foreign keys
     */
    private function enableForeignKeyChecks(): void
    {
        $driver = $this->getDatabaseDriver();
        
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    /**
     * Очистить таблицу (truncate или delete в зависимости от БД)
     */
    private function truncateTable(string $tableName): void
    {
        $driver = $this->getDatabaseDriver();
        
        if ($driver === 'sqlite') {
            // SQLite не поддерживает TRUNCATE, используем DELETE
            DB::table($tableName)->delete();
            // Сбрасываем автоинкремент для SQLite (если таблица существует)
            try {
                DB::table('sqlite_sequence')
                    ->where('name', $tableName)
                    ->delete();
            } catch (\Exception $e) {
                // Игнорируем ошибку, если таблица sqlite_sequence не существует или запись отсутствует
            }
        } else {
            // Для MySQL и других БД используем TRUNCATE
            DB::table($tableName)->truncate();
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('Вы уверены, что хотите очистить базу данных? Это действие необратимо!')) {
                $this->info('Операция отменена.');
                return 0;
            }
        }

        $this->info('Начинаю очистку базы данных...');
        $this->line('Тип БД: ' . $this->getDatabaseDriver());

        DB::beginTransaction();
        
        try {
            // Отключаем проверку foreign keys для безопасного удаления
            $this->disableForeignKeyChecks();
            
            // 1. Удаление данных в правильном порядке (с учетом foreign keys)
            $this->info('Удаление данных из таблиц...');
            
            // Таблицы раздела "Реклама" (в правильном порядке)
            $this->truncateTable('prom_payment_details');
            $this->line('  ✓ prom_payment_details');
            
            $this->truncateTable('prom_payments');
            $this->line('  ✓ prom_payments');
            
            $this->truncateTable('prom_appointments');
            $this->line('  ✓ prom_appointments');
            
            $this->truncateTable('prom_meetings');
            $this->line('  ✓ prom_meetings');
            
            $this->truncateTable('route_actions');
            $this->line('  ✓ route_actions');
            
            $this->truncateTable('routes');
            $this->line('  ✓ routes');
            
            $this->truncateTable('promoters');
            $this->line('  ✓ promoters');
            
            $this->truncateTable('districts');
            $this->line('  ✓ districts');
            
            $this->truncateTable('banks');
            $this->line('  ✓ banks');
            
            // Основные таблицы
            $this->truncateTable('cfm_operations');
            $this->line('  ✓ cfm_operations');
            
            $this->truncateTable('documents');
            $this->line('  ✓ documents');
            
            $this->truncateTable('order_persons');
            $this->line('  ✓ order_persons');
            
            $this->truncateTable('orders');
            $this->line('  ✓ orders');
            
            $this->truncateTable('master_schedules');
            $this->line('  ✓ master_schedules');
            
            $this->truncateTable('addresses');
            $this->line('  ✓ addresses');
            
            $this->truncateTable('person_phones');
            $this->line('  ✓ person_phones');
            
            $this->truncateTable('persons');
            $this->line('  ✓ persons');
            
            // Дополнительные таблицы, зависящие от users
            $this->truncateTable('interviews');
            $this->line('  ✓ interviews');
            
            $this->truncateTable('knowledge_articles');
            $this->line('  ✓ knowledge_articles');
            
            // 2. Удаление пользователей (кроме developer)
            $this->info('Удаление пользователей (кроме developer)...');
            $developerRole = Role::where('role_code', 'developer')->first();
            
            if ($developerRole) {
                // Получаем ID всех пользователей с ролью developer
                $developerUserIds = DB::table('user_roles')
                    ->where('role_id', $developerRole->role_id)
                    ->pluck('user_id')
                    ->toArray();
                
                if (!empty($developerUserIds)) {
                    // Удаляем связи user_cities и user_roles для всех, кроме developer
                    DB::table('user_cities')
                        ->whereNotIn('user_id', $developerUserIds)
                        ->delete();
                    
                    DB::table('user_roles')
                        ->whereNotIn('user_id', $developerUserIds)
                        ->delete();
                    
                    // Удаляем пользователей, кроме developer
                    DB::table('users')
                        ->whereNotIn('user_id', $developerUserIds)
                        ->delete();
                    
                    $this->line('  ✓ Пользователи удалены (кроме developer)');
                } else {
                    $this->warn('  ⚠ Пользователь с ролью developer не найден!');
                }
            } else {
                $this->warn('  ⚠ Роль developer не найдена!');
            }
            
            // 3. Очистка связей в pivot-таблицах (кроме developer)
            // Уже сделано выше при удалении пользователей
            
            // 4. Удаление городов (кроме "Управляющая компания")
            $this->info('Обработка городов...');
            
            $managementCompany = City::where('city_name', 'Управляющая компания')->first();
            
            if ($managementCompany) {
                // Удаляем все города кроме "Управляющая компания"
                DB::table('cities')
                    ->where('city_id', '!=', $managementCompany->city_id)
                    ->delete();
                
                $this->line('  ✓ Города удалены (кроме "Управляющая компания")');
            } else {
                $this->warn('  ⚠ Город "Управляющая компания" не найден!');
            }
            
            // 5. Создание города "Москва"
            $moscow = City::where('city_name', 'Москва')->first();
            
            if (!$moscow) {
                City::create([
                    'city_name' => 'Москва',
                    'city_type' => 'city',
                    'city_timezone' => 'Europe/Moscow',
                    'is_active' => true,
                ]);
                $this->line('  ✓ Город "Москва" создан');
            } else {
                // Обновляем существующий город Москва
                $moscow->update([
                    'city_type' => 'city',
                    'city_timezone' => 'Europe/Moscow',
                    'is_active' => true,
                ]);
                $this->line('  ✓ Город "Москва" обновлен');
            }
            
            // Включаем обратно проверку foreign keys
            $this->enableForeignKeyChecks();
            
            DB::commit();
            
            $this->info('');
            $this->info('✓ База данных успешно очищена!');
            $this->info('');
            $this->info('Результат:');
            $this->line('  - Остался только пользователь с ролью developer');
            $this->line('  - Города: "Управляющая компания" и "Москва"');
            $this->line('  - Все остальные данные удалены');
            
            return 0;
            
        } catch (\Exception $e) {
            // Включаем обратно проверку foreign keys в случае ошибки
            try {
                $this->enableForeignKeyChecks();
            } catch (\Exception $e2) {
                // Игнорируем ошибки при включении foreign keys
            }
            DB::rollBack();
            $this->error('Ошибка при очистке базы данных: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
