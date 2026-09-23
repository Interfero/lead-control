<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Связь CFM-операции с сотрудником (мастер / получатель зарплаты и т.п.).
 * Код уже пишет related_user_id; на shared-проде колонки не было → 500 при проведении.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            if (! Schema::hasColumn('cfm_operations', 'related_user_id')) {
                $table->foreignId('related_user_id')
                    ->nullable()
                    ->after('related_order_id')
                    ->constrained('users', 'user_id')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            if (Schema::hasColumn('cfm_operations', 'related_user_id')) {
                $table->dropConstrainedForeignId('related_user_id');
            }
        });
    }
};
