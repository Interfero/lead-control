<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Расход на юриста')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('cfm_categories')->insert([
            'cfm_cat_name' => 'Расход на юриста',
            'cfm_cat_group' => 'outflows',
            'cfm_cat_activities' => 'operating',
            'cfm_cat_adds' => 'Оплата юриста (претензии, суды, консультации)',
            'is_auto' => false,
            'is_visible' => true,
            'visible_for_roles' => null,
            'available_for_city' => true,
            'available_for_mc' => true,
            'available_for_df' => false,
            'subcategories' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Расход на юриста')
            ->delete();
    }
};
