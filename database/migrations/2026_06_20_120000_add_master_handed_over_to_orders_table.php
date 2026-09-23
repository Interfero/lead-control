<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('master_handed_over_at')->nullable()->after('order_closed_by');
            $table->unsignedBigInteger('master_handed_over_by')->nullable()->after('master_handed_over_at');

            $table->foreign('master_handed_over_by')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['master_handed_over_by']);
            $table->dropColumn(['master_handed_over_at', 'master_handed_over_by']);
        });
    }
};
