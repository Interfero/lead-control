<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Статус `review` (Проверка) уже есть в UI и GM API, но не был в MySQL ENUM —
 * POST /api/v1/gm/orders/{id}/review из СД падал 500 (Data truncated).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
            'pending', 'callback', 'not_processed', 'rejected',
            'unassigned', 'on_way', 'in_progress', 'in_progress_sd', 'review',
            'waiting_parts', 'waiting_payment',
            'completed', 'cancelled_cc', 'cancelled_city'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('orders')->where('order_status', 'review')->update(['order_status' => 'in_progress']);

        DB::statement("ALTER TABLE orders MODIFY COLUMN order_status ENUM(
            'pending', 'callback', 'not_processed', 'rejected',
            'unassigned', 'on_way', 'in_progress', 'in_progress_sd',
            'waiting_parts', 'waiting_payment',
            'completed', 'cancelled_cc', 'cancelled_city'
        ) NOT NULL DEFAULT 'pending'");
    }
};
