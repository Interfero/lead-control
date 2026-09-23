<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Справочник макетов листовок для источников заказов (отдельно от route_makets / промо).
     */
    public function up(): void
    {
        Schema::create('flyer_makets', function (Blueprint $table) {
            $table->id('flyer_maket_id');
            $table->string('flyer_maket_name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flyer_makets');
    }
};
