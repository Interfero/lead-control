<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'is_long_trip')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_long_trip')->default(false)->after('order_core');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'is_long_trip')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_long_trip');
        });
    }
};
