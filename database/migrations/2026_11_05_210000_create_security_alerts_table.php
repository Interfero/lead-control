<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();   // чья учётка
            $table->string('message', 500);
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('seen_by')->nullable();
            $table->timestamp('seen_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
