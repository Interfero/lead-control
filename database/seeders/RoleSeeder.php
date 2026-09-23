<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['role_code' => 'master', 'role_name' => 'Мастер', 'role_description' => 'Выездной специалист'],
            ['role_code' => 'ad_manager', 'role_name' => 'Менеджер по рекламе', 'role_description' => 'Работает с рекламой'],
            ['role_code' => 'order_manager', 'role_name' => 'Менеджер по заказам', 'role_description' => 'Работает с заказами'],
            ['role_code' => 'senior_manager', 'role_name' => 'Старший менеджер', 'role_description' => 'Объединяет права менеджеров'],
            ['role_code' => 'tech_director', 'role_name' => 'Технический директор', 'role_description' => 'Просмотр заказов и сотрудники'],
            ['role_code' => 'branch_head', 'role_name' => 'Руководитель филиала', 'role_description' => 'Полный доступ к филиалу'],
            ['role_code' => 'regional_director', 'role_name' => 'Региональный директор', 'role_description' => 'Управляет несколькими филиалами'],
            ['role_code' => 'call_center', 'role_name' => 'Диспетчер КЦ', 'role_description' => 'Работает с персонами'],
            ['role_code' => 'senior_dispatcher', 'role_name' => 'Старший диспетчер', 'role_description' => 'Контроль заказов, документов и отчёт за 7 дней'],
            ['role_code' => 'investor', 'role_name' => 'Инвестор', 'role_description' => 'Доступ к кассе'],
            ['role_code' => 'general_director', 'role_name' => 'Генеральный директор', 'role_description' => 'Полный доступ к управлению компанией'],
            ['role_code' => 'developer', 'role_name' => 'Разработчик', 'role_description' => 'Полный доступ'],
        ];
        
        foreach ($roles as $role) {
            Role::updateOrCreate(['role_code' => $role['role_code']], $role);
        }
    }
}
