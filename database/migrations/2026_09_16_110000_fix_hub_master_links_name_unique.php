<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * В КП один и тот же мастер встречается под несколькими написаниями
 * («Орлов Иван» и «Орлов Иван (Нов)»), которые дают одинаковый name_key.
 * Уникальность должна быть по исходному имени источника, иначе вторая
 * запись затирает первую и часть заявок остаётся без привязки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hub_master_links', function (Blueprint $table) {
            $table->dropUnique('hub_master_links_source_name_city_unique');
            $table->dropUnique('hub_master_links_source_ext_unique');
        });

        Schema::table('hub_master_links', function (Blueprint $table) {
            $table->unique(['source', 'external_master_name', 'city_id'], 'hub_master_links_source_name_city_unique');
            // один мастер КП может работать в нескольких городах — уникальность с городом
            $table->unique(['source', 'external_master_id', 'city_id'], 'hub_master_links_source_ext_city_unique');
            $table->index(['source', 'name_key', 'city_id'], 'hub_master_links_source_namekey_city_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hub_master_links', function (Blueprint $table) {
            $table->dropIndex('hub_master_links_source_namekey_city_idx');
            $table->dropUnique('hub_master_links_source_ext_city_unique');
            $table->dropUnique('hub_master_links_source_name_city_unique');
        });

        Schema::table('hub_master_links', function (Blueprint $table) {
            $table->unique(['source', 'name_key', 'city_id'], 'hub_master_links_source_name_city_unique');
            $table->unique(['source', 'external_master_id'], 'hub_master_links_source_ext_unique');
        });
    }
};
