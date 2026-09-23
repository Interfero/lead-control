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
        Schema::table('route_actions', function (Blueprint $table) {
            // Добавляем город
            if (!Schema::hasColumn('route_actions', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('route_action_id')
                      ->constrained('cities', 'city_id');
            }
            
            // Связь с промоутером (отдельная таблица, НЕ users)
            if (!Schema::hasColumn('route_actions', 'promoter_id')) {
                $table->foreignId('promoter_id')->nullable()->after('assignee_user_id')
                      ->constrained('promoters', 'promoter_id')->nullOnDelete();
            }
            
            // Количество листовок (если нет)
            if (!Schema::hasColumn('route_actions', 'leaflets_count')) {
                $table->integer('leaflets_count')->default(0)->after('maket_id');
            }
            
            if (Schema::hasColumn('route_actions', 'city_id')) {
                $table->index('city_id');
            }
            if (Schema::hasColumn('route_actions', 'promoter_id')) {
                $table->index('promoter_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_actions', function (Blueprint $table) {
            if (Schema::hasColumn('route_actions', 'city_id')) {
                $table->dropForeign(['city_id']);
                $table->dropIndex(['city_id']);
                $table->dropColumn('city_id');
            }
            if (Schema::hasColumn('route_actions', 'promoter_id')) {
                $table->dropForeign(['promoter_id']);
                $table->dropIndex(['promoter_id']);
                $table->dropColumn('promoter_id');
            }
            if (Schema::hasColumn('route_actions', 'leaflets_count')) {
                $table->dropColumn('leaflets_count');
            }
        });
    }
};
