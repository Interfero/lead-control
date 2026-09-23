<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropUnique(['source_name']);
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->string('source_phone', 32)->nullable()->after('source_name');
            $table->unique('source_phone');

            $table->enum('source_format', ['online', 'offline'])->nullable()->after('source_phone');

            $table->foreignId('city_id')->nullable()->after('source_format')->constrained('cities', 'city_id');

            $table->foreignId('flyer_maket_id')->nullable()->after('city_id')->constrained('flyer_makets', 'flyer_maket_id')->nullOnDelete();

            $table->unsignedBigInteger('superpart_partner_id')->nullable()->after('flyer_maket_id');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['flyer_maket_id']);
            $table->dropUnique(['source_phone']);
            $table->dropColumn([
                'source_phone',
                'source_format',
                'city_id',
                'flyer_maket_id',
                'superpart_partner_id',
            ]);
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->unique('source_name');
        });
    }
};
