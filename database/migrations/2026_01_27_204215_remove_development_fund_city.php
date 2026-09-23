<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Найти город "Фонд развития"
        $dfCity = DB::table('cities')
            ->where('city_name', 'Фонд развития')
            ->orWhere('city_type', 'df')
            ->first();
        
        if ($dfCity) {
            // Удалить все связанные операции
            DB::table('cfm_operations')
                ->where('city_id', $dfCity->city_id)
                ->delete();
            
            // Удалить связи пользователей с городом (если есть)
            DB::table('user_cities')
                ->where('city_id', $dfCity->city_id)
                ->delete();
            
            // Удалить город
            DB::table('cities')
                ->where('city_id', $dfCity->city_id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстановить город "Фонд развития" (если нужно откатить миграцию)
        // Операции восстановить нельзя, так как они были удалены
        $exists = DB::table('cities')
            ->where('city_name', 'Фонд развития')
            ->exists();
        
        if (!$exists) {
            DB::table('cities')->insert([
                'city_name' => 'Фонд развития',
                'city_type' => 'df',
                'city_timezone' => 'Asia/Almaty',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
