<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавление источника к звонку (линия, на которую позвонили → источник, напр. "Листовка Артём")
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->after('operator_id');
            $table->foreign('source_id')->references('source_id')->on('sources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropColumn('source_id');
        });
    }
};
