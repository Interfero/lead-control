<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sources')
            ->whereIn('source_kind', ['partner_flyer', 'rk'])
            ->where(function ($q) {
                $q->whereNotNull('superpart_local_source_id')
                    ->orWhere('available_for_superpart', true)
                    ->orWhere('source_kind', 'partner_flyer');
            })
            ->update([
                'source_kind' => 'party',
                'available_for_superpart' => true,
            ]);

        DB::table('sources')
            ->where('source_kind', 'rk')
            ->where(function ($q) {
                $q->whereNotNull('flyer_maket_id')
                    ->orWhereRaw('LOWER(source_name) LIKE ?', ['%листов%']);
            })
            ->update(['source_kind' => 'flyer']);

        DB::table('sources')
            ->where('source_kind', 'rk')
            ->update(['source_kind' => 'flyer']);

        DB::table('sources')
            ->where('source_kind', 'partner_flyer')
            ->update([
                'source_kind' => 'party',
                'available_for_superpart' => true,
            ]);

        Schema::table('sources', function (Blueprint $table) {
            $table->string('source_kind', 16)->default('flyer')->change();
        });
    }

    public function down(): void
    {
        // Обратная миграция не восстанавливает прежнее разбиение.
    }
};
