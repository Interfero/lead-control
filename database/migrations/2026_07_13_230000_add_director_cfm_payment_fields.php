<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            if (! Schema::hasColumn('cfm_operations', 'cfm_payer')) {
                $table->string('cfm_payer', 255)->nullable()->after('cfm_subcat');
            }
            if (! Schema::hasColumn('cfm_operations', 'cfm_recipient')) {
                $table->string('cfm_recipient', 255)->nullable()->after('cfm_payer');
            }
            if (! Schema::hasColumn('cfm_operations', 'external_cfm_ref')) {
                $table->string('external_cfm_ref', 64)->nullable()->after('cfm_recipient');
            }
        });

        $categories = [
            [
                'cfm_cat_name' => 'Оплата партов',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
            ],
            [
                'cfm_cat_name' => 'Расход Уровень',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
            ],
            [
                'cfm_cat_name' => 'Инкассация',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
            ],
            [
                'cfm_cat_name' => 'Комиссия инкаса',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
            ],
        ];

        foreach ($categories as $cat) {
            $exists = DB::table('cfm_categories')->where('cfm_cat_name', $cat['cfm_cat_name'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('cfm_categories')->insert(array_merge($cat, [
                'is_auto' => false,
                'is_visible' => true,
                'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director',
                'available_for_city' => true,
                'available_for_mc' => true,
                'available_for_df' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            foreach (['external_cfm_ref', 'cfm_recipient', 'cfm_payer'] as $col) {
                if (Schema::hasColumn('cfm_operations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
