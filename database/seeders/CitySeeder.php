<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Базовый сидер городов.
 * 
 * DEPRECATED: Используйте ProductionCitySeeder для production.
 * Этот сидер оставлен для обратной совместимости.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        // Создаём только Управляющую компанию
        // Города создаются через ProductionCitySeeder
        City::updateOrCreate(
            ['city_name' => 'Управляющая компания'],
            [
                'city_type' => 'mc',
                'city_timezone' => 'Europe/Moscow',
                'is_active' => true,
            ]
        );
    }
}
