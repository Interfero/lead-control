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
        Schema::table('cfm_operations', function (Blueprint $table) {
            // Подстатья для операций (Листовки, Аренда Офиса, Аренда Квартиры, Выдача Дивидендов)
            $table->string('cfm_subcat', 100)->nullable()->after('cfm_adds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            $table->dropColumn('cfm_subcat');
        });
    }
};
