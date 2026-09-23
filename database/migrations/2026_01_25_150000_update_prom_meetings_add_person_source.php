<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prom_meetings', function (Blueprint $table) {
            // Добавляем поле person_source
            $table->enum('person_source', ['head_hunter', 'olx', 'recommendation'])->nullable()->after('leaflets_issued');
            
            // Удаляем внешний ключ и поле source_id (если есть)
            if (Schema::hasColumn('prom_meetings', 'source_id')) {
                $table->dropForeign(['source_id']);
                $table->dropColumn('source_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prom_meetings', function (Blueprint $table) {
            $table->dropColumn('person_source');
            $table->foreignId('source_id')->nullable()->constrained('sources');
        });
    }
};
