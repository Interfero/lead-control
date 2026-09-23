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
        Schema::create('districts', function (Blueprint $table) {
            $table->id('district_id');
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            $table->string('district_name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['city_id', 'district_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
