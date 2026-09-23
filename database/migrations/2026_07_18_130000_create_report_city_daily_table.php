<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_city_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->date('report_date');

            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('closed_total')->default(0);
            $table->unsignedInteger('closed_our')->default(0);
            $table->unsignedInteger('closed_partner')->default(0);
            $table->bigInteger('turnover')->default(0);
            $table->bigInteger('net')->default(0);
            $table->bigInteger('parts')->default(0);
            $table->unsignedInteger('complaints_count')->default(0);
            $table->bigInteger('promo_pay')->default(0);
            $table->unsignedInteger('warranty_closed')->default(0);
            $table->unsignedInteger('repeat_closed')->default(0);
            $table->unsignedInteger('non_core_closed')->default(0);
            $table->unsignedInteger('refusals')->default(0);
            $table->unsignedInteger('rejected')->default(0);

            $table->boolean('stale')->default(false);
            $table->timestamp('rebuilt_at')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'report_date']);
            $table->index(['report_date', 'stale']);
            $table->foreign('city_id')->references('city_id')->on('cities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_city_daily');
    }
};
