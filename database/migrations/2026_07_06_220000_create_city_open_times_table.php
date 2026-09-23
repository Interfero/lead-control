<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_open_times', function (Blueprint $table) {
            $table->id('city_open_time_id');
            $table->foreignId('city_id')->constrained('cities', 'city_id')->cascadeOnDelete();
            $table->date('begin_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('time_from');
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();

            $table->index(['city_id', 'begin_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_open_times');
    }
};
