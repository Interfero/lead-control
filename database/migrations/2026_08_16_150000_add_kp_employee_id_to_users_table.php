<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'kp_employee_id')) {
                $table->unsignedBigInteger('kp_employee_id')->nullable()->unique()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'kp_employee_id')) {
                $table->dropUnique(['kp_employee_id']);
                $table->dropColumn('kp_employee_id');
            }
        });
    }
};
