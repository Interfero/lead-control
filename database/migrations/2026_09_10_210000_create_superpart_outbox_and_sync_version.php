<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'sync_version')) {
                $table->unsignedBigInteger('sync_version')->default(0)->after('partner_user_id');
                $table->index('sync_version', 'orders_sync_version_idx');
            }
        });

        if (! Schema::hasTable('superpart_outbox')) {
            Schema::create('superpart_outbox', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('order_version');
                $table->string('event_type', 64);
                $table->json('payload');
                $table->string('status', 16)->default('pending')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->string('last_error', 512)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('sent_at')->nullable();

                $table->index(['status', 'next_attempt_at'], 'outbox_dispatch_idx');
                $table->index(['order_id', 'order_version'], 'outbox_order_version_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('superpart_outbox');
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'sync_version')) {
                $table->dropIndex('orders_sync_version_idx');
                $table->dropColumn('sync_version');
            }
        });
    }
};
