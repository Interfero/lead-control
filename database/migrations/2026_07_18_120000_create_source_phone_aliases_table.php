<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_phone_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique('phone');
            $table->index('source_id');
            $table->foreign('source_id')
                ->references('source_id')
                ->on('sources')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_phone_aliases');
    }
};
