<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sources')
            ->whereNotNull('superpart_local_source_id')
            ->update(['source_kind' => 'party']);

        DB::table('sources')
            ->whereNull('superpart_local_source_id')
            ->where(function ($q) {
                $q->whereNotNull('flyer_maket_id')
                    ->orWhereRaw('LOWER(source_name) LIKE ?', ['%листов%']);
            })
            ->update(['source_kind' => 'flyer']);

        DB::table('sources')
            ->whereNull('superpart_local_source_id')
            ->where('source_kind', 'rk')
            ->where(function ($q) {
                $q->whereRaw('LOWER(source_name) LIKE ?', ['%партнер%'])
                    ->orWhereRaw('LOWER(source_name) LIKE ?', ['%партнёр%'])
                    ->orWhereRaw('LOWER(source_name) LIKE ?', ['%partner%']);
            })
            ->update(['source_kind' => 'partner_flyer']);
    }

    public function down(): void
    {
        DB::table('sources')
            ->whereIn('source_kind', ['flyer', 'partner_flyer'])
            ->update(['source_kind' => 'rk']);
    }
};
