<?php

namespace Database\Seeders;

use App\Models\CfmCategory;
use Illuminate\Database\Seeder;

class CfmCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Автоматические (не видны при создании)
            ['cfm_cat_name' => 'Перемещение (поступление)', 'cfm_cat_group' => 'inflows', 'cfm_cat_activities' => 'operating', 'is_auto' => true, 'is_visible' => false],
            ['cfm_cat_name' => 'Поступление с Заказов', 'cfm_cat_group' => 'inflows', 'cfm_cat_activities' => 'operating', 'is_auto' => true, 'is_visible' => false],
            
            // Ручные - Поступления
            ['cfm_cat_name' => 'Прочее Поступление', 'cfm_cat_group' => 'inflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Штраф (приход)', 'cfm_cat_group' => 'inflows', 'cfm_cat_activities' => 'operating', 'cfm_cat_adds' => 'Штрафы сотрудников (внесение в кассу)'],
            
            // Ручные - Выбытия операционные
            ['cfm_cat_name' => 'Перемещение (выбытие)', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Зарплата промоутеров', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Листовки', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'QR Point', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'HeadHunter', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'OLX', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Объявление Авито', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Зарплата Директора Филиала', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Зарплата Менеджера по заказам', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Зарплата Менеджера по рекламе', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Зарплата Технического Директора', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Зарплата сотрудников КЦ', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating', 'visible_for_roles' => 'developer'],
            ['cfm_cat_name' => 'Зарплата Бухгалтера', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Содержание офиса', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Аренда Офиса', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Аренда Квартиры', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Расходы на персонал', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Штраф', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Возвраты клиентам', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Оплата партов', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating', 'cfm_cat_adds' => 'Выплата партнёру через посредника', 'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director'],
            ['cfm_cat_name' => 'Расход партнерам', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating', 'cfm_cat_adds' => 'Перевод: директор забирает деньги за партнёрские заказы без посредника', 'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director'],
            ['cfm_cat_name' => 'Командировочные расходы', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Налоги', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Телефония', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating', 'visible_for_roles' => 'developer'],
            ['cfm_cat_name' => 'Прочее Выбытие', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            
            // Финансовая деятельность
            ['cfm_cat_name' => 'Инкас', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'financing'],
            ['cfm_cat_name' => 'Подписки', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating'],
            ['cfm_cat_name' => 'Выдача Дивидендов', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'financing', 'visible_for_roles' => 'developer'],
            ['cfm_cat_name' => 'Корпоративные расходы', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'financing', 'visible_for_roles' => 'developer'],
            
            // Инвестиционная деятельность
            ['cfm_cat_name' => 'Закуп оборудования', 'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'investing'],
        ];
        
        foreach ($categories as $cat) {
            CfmCategory::updateOrCreate(
                ['cfm_cat_name' => $cat['cfm_cat_name']],
                array_merge(['is_auto' => false, 'is_visible' => true, 'visible_for_roles' => null], $cat)
            );
        }
    }
}
