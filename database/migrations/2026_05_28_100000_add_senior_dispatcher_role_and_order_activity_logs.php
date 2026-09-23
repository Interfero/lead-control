<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('role_code', 'senior_dispatcher')->exists()) {
            DB::table('roles')->insert([
                'role_code' => 'senior_dispatcher',
                'role_name' => 'Старший диспетчер',
                'role_description' => 'Контроль заказов, закрытых заявок, документов и отчёт за 7 дней',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('order_activity_logs')) {
            Schema::create('order_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 64);
                $table->string('field_name', 64)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
                $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
                $table->index(['order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_activity_logs');
        DB::table('roles')->where('role_code', 'senior_dispatcher')->delete();
    }
};
