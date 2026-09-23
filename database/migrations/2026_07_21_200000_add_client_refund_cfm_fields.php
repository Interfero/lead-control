<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Возврат клиенту: сумма с кассы (amount_cfm) + сумма с мастера (amount_from_master).
 * Статья «Возвраты клиентам» — доступна диспетчерам.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            if (! Schema::hasColumn('cfm_operations', 'amount_from_master')) {
                $table->unsignedInteger('amount_from_master')->nullable()->after('amount_cfm');
            }
        });

        $exists = DB::table('cfm_categories')->where('cfm_cat_name', 'Возвраты клиентам')->exists();
        if (! $exists) {
            DB::table('cfm_categories')->insert([
                'cfm_cat_name' => 'Возвраты клиентам',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'is_auto' => false,
                'is_visible' => true,
                'visible_for_roles' => 'developer,call_center,senior_dispatcher,branch_head,regional_director,senior_manager,general_director',
                'available_for_city' => true,
                'available_for_mc' => true,
                'available_for_df' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('cfm_categories')
                ->where('cfm_cat_name', 'Возвраты клиентам')
                ->update([
                    'is_visible' => true,
                    'visible_for_roles' => 'developer,call_center,senior_dispatcher,branch_head,regional_director,senior_manager,general_director',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            if (Schema::hasColumn('cfm_operations', 'amount_from_master')) {
                $table->dropColumn('amount_from_master');
            }
        });
    }
};
