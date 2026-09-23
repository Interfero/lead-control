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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_blacklisted')->default(false)->after('user_fired_at');
            $table->text('blacklist_reason')->nullable()->after('is_blacklisted');
            $table->timestamp('blacklisted_at')->nullable()->after('blacklist_reason');
            $table->unsignedBigInteger('blacklisted_by')->nullable()->after('blacklisted_at');
            
            $table->foreign('blacklisted_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blacklisted_by']);
            $table->dropColumn(['is_blacklisted', 'blacklist_reason', 'blacklisted_at', 'blacklisted_by']);
        });
    }
};
