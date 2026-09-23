<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Миграция статусов заказов (план 19.03):
 * - Перевести старые значения в используемые: unassigned, on_way -> pending;
 *   waiting_parts, waiting_payment -> in_progress.
 * - Коды unassigned, on_way, waiting_parts, waiting_payment больше не используются в UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Данные: старые статусы -> новые
        DB::table('orders')
            ->whereIn('order_status', ['unassigned', 'on_way'])
            ->update(['order_status' => 'pending']);

        DB::table('orders')
            ->whereIn('order_status', ['waiting_parts', 'waiting_payment'])
            ->update(['order_status' => 'in_progress']);

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            // Добавить not_processed и rejected в enum (для нового UI)
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
                'pending', 'callback', 'not_processed', 'rejected',
                'unassigned', 'on_way', 'in_progress', 'in_progress_sd',
                'waiting_parts', 'waiting_payment',
                'completed', 'cancelled_cc', 'cancelled_city'
            ) NOT NULL DEFAULT 'pending'");
        }
        // SQLite: колонка TEXT с CHECK в 2026_01_27; при необходимости добавить
        // not_processed/rejected в CHECK — пересоздать таблицу в отдельной миграции.
    }

    public function down(): void
    {
        // Обратный перенос не выполняем: старые коды сняты с использования.
    }
};
