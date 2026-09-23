<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Для production запуска:
     * php artisan migrate:fresh --seed
     * 
     * Это создаст:
     * - Все справочники (роли, категории ДДС, банки, источники)
     * - 44 города + Управляющую компанию
     * - Директора и старшего менеджера для каждого города
     * - Одного разработчика с полным доступом
     */
    public function run(): void
    {
        // 1. Справочники (обязательные, порядок важен!)
        $this->call([
            RoleSeeder::class,           // Роли пользователей
            SourceSeeder::class,         // Источники заказов
            CfmCategorySeeder::class,    // Категории ДДС
            BankSeeder::class,           // Банки для выплат
        ]);
        
        // 2. Города
        $this->call([
            ProductionCitySeeder::class, // 44 города + УК
        ]);
        
        // 3. Пользователи
        $this->call([
            ProductionUserSeeder::class, // Директора, менеджеры, разработчик
        ]);

        // 3b. Единое окно заказов (подключения + маппинг статусов)
        $this->call([
            DeskSeeder::class,
        ]);
        
        // 4. Дополнительные данные (опционально)
        // Раскомментируйте при необходимости:
        // $this->call([
        //     KnowledgeSeeder::class,   // База знаний (статьи)
        //     DistrictSeeder::class,    // Районы городов
        // ]);
    }
}
