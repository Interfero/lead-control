<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Переименовать "Старший менеджер" → "Менеджер филиала"
        DB::table('roles')
            ->where('role_code', 'senior_manager')
            ->update(['role_name' => 'Менеджер филиала']);

        // Деактивировать устаревшие роли
        DB::table('roles')
            ->whereIn('role_code', ['investor', 'ad_manager', 'order_manager'])
            ->update(['is_active' => false]);

        // Добавить роль Генерального директора (если ещё нет)
        if (!DB::table('roles')->where('role_code', 'general_director')->exists()) {
            DB::table('roles')->insert([
                'role_code'        => 'general_director',
                'role_name'        => 'Генеральный директор',
                'role_description' => 'Полный просмотр всех разделов. Создание диспетчеров КЦ.',
                'is_active'        => true,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('role_code', 'senior_manager')
            ->update(['role_name' => 'Старший менеджер']);

        DB::table('roles')
            ->whereIn('role_code', ['investor', 'ad_manager', 'order_manager'])
            ->update(['is_active' => true]);

        DB::table('roles')
            ->where('role_code', 'general_director')
            ->delete();
    }
};
