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
        Schema::create('prom_payment_details', function (Blueprint $table) {
            $table->id('detail_id');
            $table->foreignId('payment_id')->constrained('prom_payments', 'payment_id')->onDelete('cascade');
            $table->foreignId('route_action_id')->constrained('route_actions', 'route_action_id');
            
            // Денормализованные данные для отчёта
            $table->date('action_date');
            $table->integer('leaflets_count');
            $table->string('route_name', 255)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prom_payment_details');
    }
};
