<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            if (! Schema::hasColumn('sources', 'superpart_local_source_id')) {
                $table->unsignedBigInteger('superpart_local_source_id')
                    ->nullable()
                    ->unique()
                    ->after('superpart_partner_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            if (Schema::hasColumn('sources', 'superpart_local_source_id')) {
                $table->dropUnique(['superpart_local_source_id']);
                $table->dropColumn('superpart_local_source_id');
            }
        });
    }
};
