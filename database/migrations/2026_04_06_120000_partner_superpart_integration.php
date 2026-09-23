<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_user_id')->nullable()->after('source_id')
                ->comment('user_id партнёра в SuperPart (внешняя система)');
        });

        Schema::create('integration_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 8); // in | out
            $table->string('channel', 64)->default('superpart');
            $table->string('method', 8);
            $table->string('url', 512)->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('request_summary')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('partner_order_idempotency', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->primary();
            $table->unsignedBigInteger('order_id');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_order_idempotency');
        Schema::dropIfExists('integration_api_logs');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('partner_user_id');
        });
    }
};
