<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable()->after('order_closed_at');
            }
            if (! Schema::hasColumn('orders', 'in_progress_at')) {
                $table->timestamp('in_progress_at')->nullable()->after('status_changed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'in_progress_at')) {
                $table->dropColumn('in_progress_at');
            }
            if (Schema::hasColumn('orders', 'status_changed_at')) {
                $table->dropColumn('status_changed_at');
            }
        });
    }
};
