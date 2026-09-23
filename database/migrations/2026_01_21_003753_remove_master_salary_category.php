<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Найти ID категории "Зарплата мастера"
        $category = DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Зарплата мастера')
            ->first();
        
        if ($category) {
            // Удалить все операции с этой категорией
            DB::table('cfm_operations')
                ->where('cfm_cat_id', $category->cfm_cat_id)
                ->delete();
            
            // Удалить саму категорию
            DB::table('cfm_categories')
                ->where('cfm_cat_id', $category->cfm_cat_id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстановление не предусмотрено - данные удалены безвозвратно
    }
};
