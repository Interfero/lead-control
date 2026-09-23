<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id('complaint_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('complaint_type'); // quality, service, timing, other
            $table->text('complaint_text');
            $table->text('complaint_result')->nullable();
            $table->string('complaint_status')->default('new'); // new, in_progress, resolved, rejected
            $table->timestamp('complaint_created_at')->nullable();
            $table->unsignedBigInteger('complaint_created_by')->nullable();
            $table->timestamp('complaint_closed_at')->nullable();
            $table->unsignedBigInteger('complaint_closed_by')->nullable();

            $table->foreign('person_id')->references('person_id')->on('persons')->nullOnDelete();
            $table->foreign('order_id')->references('order_id')->on('orders')->nullOnDelete();
            $table->foreign('city_id')->references('city_id')->on('cities')->nullOnDelete();
            $table->foreign('complaint_created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('complaint_closed_by')->references('user_id')->on('users')->nullOnDelete();

            $table->index('complaint_status');
            $table->index('complaint_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
