<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            // Добавляем недостающие поля
            if (!Schema::hasColumn('routes', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('route_id')
                      ->constrained('cities', 'city_id');
            }
            if (!Schema::hasColumn('routes', 'district_id')) {
                $table->foreignId('district_id')->nullable()->after('city_id')
                      ->constrained('districts', 'district_id')->nullOnDelete();
            }
            if (!Schema::hasColumn('routes', 'route_apartments_count')) {
                $table->integer('route_apartments_count')->default(0)->after('route_entrances_count');
            }
            
            // route_boxes_count используем как "Квартиры на маршруте"
            // route_entrances_count уже есть - "Подъездов на маршруте"
            
            if (Schema::hasColumn('routes', 'city_id')) {
                $table->index('city_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            if (Schema::hasColumn('routes', 'city_id')) {
                $table->dropForeign(['city_id']);
                $table->dropIndex(['city_id']);
                $table->dropColumn('city_id');
            }
            if (Schema::hasColumn('routes', 'district_id')) {
                $table->dropForeign(['district_id']);
                $table->dropColumn('district_id');
            }
            if (Schema::hasColumn('routes', 'route_apartments_count')) {
                $table->dropColumn('route_apartments_count');
            }
        });
    }
};
