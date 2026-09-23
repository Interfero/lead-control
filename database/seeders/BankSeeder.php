<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

/**
 * Сидер банков для выплат промоутерам
 */
class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            // Российские банки
            'Сбербанк',
            'Тинькофф',
            'ВТБ',
            'Альфа-Банк',
            'Газпромбанк',
            'Россельхозбанк',
            'Открытие',
            'Райффайзенбанк',
            'Совкомбанк',
            'Почта Банк',
            'Росбанк',
            'Промсвязьбанк',
            'Ак Барс Банк',
            'Уралсиб',
            'МКБ',
            'Наличные',
        ];
        
        foreach ($banks as $bankName) {
            Bank::updateOrCreate(
                ['bank_name' => $bankName],
                ['is_active' => true]
            );
        }
    }
}
