<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавить тип города
        Schema::table('cities', function (Blueprint $table) {
            $table->enum('city_type', ['city', 'mc', 'df'])->default('city')->after('city_name');
        });
        
        // Добавить флаги доступности в категории
        Schema::table('cfm_categories', function (Blueprint $table) {
            $table->boolean('available_for_city')->default(true)->after('visible_for_roles');
            $table->boolean('available_for_mc')->default(false)->after('available_for_city');
            $table->boolean('available_for_df')->default(false)->after('available_for_mc');
            $table->string('subcategories', 500)->nullable()->after('available_for_df'); // JSON-список подкатегорий
        });
        
        // Создать город "Управляющая компания"
        DB::table('cities')->insert([
            'city_name' => 'Управляющая компания',
            'city_type' => 'mc',
            'city_timezone' => 'Asia/Almaty',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Создать город "Фонд развития"
        DB::table('cities')->insert([
            'city_name' => 'Фонд развития',
            'city_type' => 'df',
            'city_timezone' => 'Asia/Almaty',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Обновить категории: по умолчанию доступны для города
        DB::table('cfm_categories')->update(['available_for_city' => true]);
        
        // Особые статьи - только для УК и ФР, не для города
        DB::table('cfm_categories')
            ->whereIn('cfm_cat_name', ['Выдача Дивидендов', 'Телефония', 'Зарплата сотрудников КЦ', 'Корпоративные расходы'])
            ->update([
                'available_for_city' => false,
                'available_for_mc' => true,
                'available_for_df' => true,
            ]);
        
        // Инкас - также доступен для УК (туда перечисляются деньги)
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Инкас')
            ->update([
                'available_for_mc' => true,
            ]);
        
        // Перемещение (поступление) - для всех типов
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Перемещение (поступление)')
            ->update([
                'available_for_mc' => true,
                'available_for_df' => true,
            ]);
        
        // Перемещение (выбытие) - для всех типов
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Перемещение (выбытие)')
            ->update([
                'available_for_mc' => true,
                'available_for_df' => true,
            ]);
            
        // Обновить подкатегории для статей
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Листовки')
            ->update(['subcategories' => json_encode(['Печать листовок', 'Доставка листовок'])]);
            
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Аренда Офиса')
            ->update(['subcategories' => json_encode(['Аренда', 'К/У'])]);
            
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Аренда Квартиры')
            ->update(['subcategories' => json_encode(['Аренда', 'К/У'])]);
            
        DB::table('cfm_categories')
            ->where('cfm_cat_name', 'Выдача Дивидендов')
            ->update(['subcategories' => json_encode(['Евгений', 'Никита'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('city_type');
        });
        
        Schema::table('cfm_categories', function (Blueprint $table) {
            $table->dropColumn(['available_for_city', 'available_for_mc', 'available_for_df', 'subcategories']);
        });
        
        // Удалить созданные города
        DB::table('cities')->whereIn('city_name', ['Управляющая компания', 'Фонд развития'])->delete();
    }
};
