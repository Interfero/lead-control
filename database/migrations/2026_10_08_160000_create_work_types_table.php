<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Справочник видов работ — заменяет хардкод Order::EQUIPMENT_TYPES/CORE_EQUIPMENT.
     * code — тот же строковый код, что раньше был ключом массива (и что уже используется
     * как crm_code в SuperPart, в вебхуках, в GM/Desk API) — намеренно не числовой id,
     * чтобы не трогать orders.equipment_type и все внешние интеграции, завязанные на строку.
     */
    public function up(): void
    {
        Schema::create('work_types', function (Blueprint $table) {
            $table->id('work_type_id');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->enum('order_core', ['core', 'non_core', 'other'])->default('non_core');
            // % от (оплата - запчасти), начисляемый партнёру СуперПартс.
            $table->decimal('partner_rate', 5, 2)->default(30);
            // Потолок начисления партнёру по одной заявке, ₽.
            $table->decimal('partner_cap', 10, 2)->default(2500);
            // Задел на будущее: % зарплаты мастеру по виду работ (сейчас не используется в расчёте).
            $table->decimal('master_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Зеркало текущего хардкода Order::EQUIPMENT_TYPES/CORE_EQUIPMENT — тот же порядок, те же
        // коды и подписи, те же ставки, что уже используются (30%/40%+потолок 2500 — см. SuperPart
        // apps/reference/migrations/0003_seed_work_category_payout_rate.py). Это не меняет поведение,
        // только переносит источник данных.
        \DB::table('work_types')->insert([
            ['code' => 'tv', 'name' => 'Телевизоры', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'computer', 'name' => 'Пк/Моноблоки/Ноутбуки', 'order_core' => 'core', 'partner_rate' => 40, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'printer', 'name' => 'Ремонт принтера/Настройка без пк/Прошивка принтера', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'monitor', 'name' => 'Мониторы/Видеокарты (без ПК)', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'boiler', 'name' => 'Бойлеры', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'oven', 'name' => 'Духовки/Духовые шкафы/Электроплиты', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'conditioner', 'name' => 'Кондиционеры/Сплит-системы', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'coffee_machine', 'name' => 'Кофемашины', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'dishwasher', 'name' => 'Посудомоечные машины', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'washing_machine', 'name' => 'Стиральные/Сушильные машины', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'fridge', 'name' => 'Холодильники/Морозилки', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'game_console', 'name' => 'Чистка и настройка PS/Xbox/Nintendo switch/Steam Deck', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'data_recovery', 'name' => 'Восстановление данных с носителей', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'cable', 'name' => 'Обжим кабеля без пк', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'router', 'name' => 'Роутеры', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'smartphone', 'name' => 'Телефоны/Планшеты', 'order_core' => 'non_core', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'other_device', 'name' => 'Тип техники «ПРОЧАЯ»', 'order_core' => 'other', 'partner_rate' => 30, 'partner_cap' => 2500, 'master_rate' => 0, 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_types');
    }
};
