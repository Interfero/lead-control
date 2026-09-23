<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Production сидер для городов.
 * Создаёт все рабочие города + Управляющую компанию.
 */
class ProductionCitySeeder extends Seeder
{
    public function run(): void
    {
        // Список городов с часовыми поясами
        $cities = [
            // Город => Часовой пояс
            'Новосибирск' => 'Asia/Novosibirsk',
            'Краснодар' => 'Europe/Moscow',
            'Калуга' => 'Europe/Moscow',
            'Курск' => 'Europe/Moscow',
            'СПБ З' => 'Europe/Moscow',
            'Тверь' => 'Europe/Moscow',
            'Белгород' => 'Europe/Moscow',
            'Стерлитамак' => 'Asia/Yekaterinburg',
            'Череповец' => 'Europe/Moscow',
            'Набережные Челны' => 'Europe/Moscow',
            'Красноярск' => 'Asia/Krasnoyarsk',
            'Псков' => 'Europe/Moscow',
            'Анапа' => 'Europe/Moscow',
            'Севастополь' => 'Europe/Moscow',
            'Волгоград' => 'Europe/Volgograd',
            'Тюмень' => 'Asia/Yekaterinburg',
            'Рязань' => 'Europe/Moscow',
            'Петрозаводск' => 'Europe/Moscow',
            'Смоленск' => 'Europe/Moscow',
            'Нижнекамск' => 'Europe/Moscow',
            'Москва 1' => 'Europe/Moscow',
            'Тула' => 'Europe/Moscow',
            'Пятигорск' => 'Europe/Moscow',
            'Барнаул' => 'Asia/Barnaul',
            'Красногорск (МСК)' => 'Europe/Moscow',
            'Вологда' => 'Europe/Moscow',
            'Сочи' => 'Europe/Moscow',
            'Ставрополь' => 'Europe/Moscow',
            'Петропавловск-Камчатский' => 'Asia/Kamchatka',
            'Долгопрудный (МСК)' => 'Europe/Moscow',
            'Сыктывкар' => 'Europe/Moscow',
            'Оренбург' => 'Asia/Yekaterinburg',
            'Новокузнецк' => 'Asia/Novokuznetsk',
            'Йошкар-Ола' => 'Europe/Moscow',
            'Видное (МСК)' => 'Europe/Moscow',
            'Кемерово' => 'Asia/Novokuznetsk',
            'Подольск (МСК)' => 'Europe/Moscow',
            'Симферополь' => 'Europe/Moscow',
            'Коммунарка (МСК)' => 'Europe/Moscow',
            'Бийск' => 'Asia/Barnaul',
            'Майкоп' => 'Europe/Moscow',
            'Новороссийск' => 'Europe/Moscow',
            'Орехово-Зуево (МСК)' => 'Europe/Moscow',
            'Армавир' => 'Europe/Moscow',
        ];
        
        // Создаём города
        foreach ($cities as $cityName => $timezone) {
            City::updateOrCreate(
                ['city_name' => $cityName],
                [
                    'city_type' => 'city',
                    'city_timezone' => $timezone,
                    'is_active' => true,
                ]
            );
        }
        
        // Создаём Управляющую компанию
        City::updateOrCreate(
            ['city_name' => 'Управляющая компания'],
            [
                'city_type' => 'mc',
                'city_timezone' => 'Europe/Moscow',
                'is_active' => true,
            ]
        );
        
        $this->command->info('Создано ' . count($cities) . ' городов + Управляющая компания');
    }
}
