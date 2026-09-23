<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('city_timezone', 50)->default('Europe/Moscow')->after('city_name');
        });
        
        // Обновляем существующие города с правильными timezone (если есть)
        // Примечание: Для production используется ProductionCitySeeder, который устанавливает timezone
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('city_timezone');
        });
    }
};
