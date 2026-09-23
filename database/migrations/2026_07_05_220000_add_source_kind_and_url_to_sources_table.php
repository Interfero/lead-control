<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('source_kind', 16)->default('rk')->after('source_format');
            $table->string('source_url', 2048)->nullable()->after('source_kind');
            $table->boolean('use_source_url')->default(false)->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['source_kind', 'source_url', 'use_source_url']);
        });
    }
};
