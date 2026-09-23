<?php

namespace App\Services;

use App\Models\User;

class NavigationService
{
    /**
     * Получить структуру навигации для пользователя
     */
    public function getMenuForUser(User $user): array
    {
        $menu = [];

        // Главная — ВРЕМЕННО ОТКЛЮЧЕНО
        // $menu[] = [
        //     'name' => 'Главная',
        //     'route' => 'dashboard',
        //     'icon' => 'home',
        //     'active' => request()->routeIs('dashboard'),
        // ];

        $hub = rtrim((string) config('services.hub.public_url'), '/');
        if ($hub !== '') {
            $menu[] = [
                'name' => 'Хаб',
                'url' => $hub.'/',
                'icon' => 'dashboard',
                'active' => false,
            ];
        }

        if ($this->canAccessDispatcherDashboard($user)) {
            $menu[] = [
                'name' => 'Дашборд',
                'route' => 'dispatcher.dashboard',
                'icon' => 'dashboard',
                'active' => request()->routeIs('dispatcher.dashboard'),
            ];
        }

        // Заказы — URL со сбросом session-фильтров (как логотип LC)
        if ($this->canAccessOrders($user)) {
            $menu[] = [
                'name' => 'Заказы',
                'route' => 'orders.index',
                'url' => route('orders.index', ['clear_filters' => 1]),
                'icon' => 'orders',
                'active' => request()->routeIs('orders.*'),
            ];
        }

        if ($this->canAccessCityOpenTimes($user)) {
            $menu[] = [
                'name' => 'Закрепление времени',
                'route' => 'city-open-times.index',
                'icon' => 'calendar',
                'active' => request()->routeIs('city-open-times.*'),
            ];
        }

        // Клиенты КЦ
        if ($this->canAccessPersons($user)) {
            $personsChildren = [
                ['name' => 'Клиенты', 'route' => 'persons.index'],
            ];
            if ($user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher'])) {
                $personsChildren[] = ['name' => 'Отзывы', 'route' => 'complaints.reviews'];
            }
            $menu[] = [
                'name' => 'Клиенты КЦ',
                'icon' => 'persons',
                'active' => request()->routeIs('persons.*') || request()->routeIs('complaints.reviews*'),
                'children' => $personsChildren,
            ];
        }

        // Касса
        if ($this->canAccessCfm($user)) {
            $cfmChildren = [
                ['name' => 'Кассовые операции', 'route' => 'cfm.index'],
            ];
            if ($this->canAccessCfmSettlement($user)) {
                $cfmChildren[] = ['name' => 'Рассчитать мастера', 'route' => 'cfm.master-settlement.index'];
            }
            $cfmChildren[] = ['name' => 'Расчёт ЗП', 'route' => 'cfm.salary.index'];
            $cfmChildren[] = ['name' => 'Отчёт по кассе', 'route' => 'cfm.summary'];
            if ($user->hasAnyRole(['developer', 'general_director', 'senior_dispatcher'])) {
                $cfmChildren[] = ['name' => 'Оплата заявок', 'route' => 'cfm.order-payments'];
            }

            $menu[] = [
                'name' => 'Касса',
                'icon' => 'cfm',
                'active' => request()->routeIs('cfm.*'),
                'children' => $cfmChildren,
            ];
        }

        // Сотрудники
        if ($this->canAccessHr($user)) {
            $children = [];

            if ($this->canAccessHrList($user)) {
                $children[] = ['name' => 'Список сотрудников', 'route' => 'hr.index'];
            }

            if ($this->canAccessHrMasters($user)) {
                $children[] = ['name' => 'Рейтинг мастеров', 'route' => 'hr.masters'];
                if ($this->canAccessHrRosterNav($user)) {
                    $children[] = ['name' => 'График мастеров', 'route' => 'hr.roster'];
                }
            }

            if (! empty($children)) {
                $menu[] = [
                    'name' => 'Сотрудники',
                    'icon' => 'hr',
                    'active' => request()->routeIs('hr.*'),
                    'children' => $children,
                ];
            }
        }

        // Отчёты
        if ($this->canAccessReports($user)) {
            $menu[] = [
                'name' => 'Отчёты',
                'icon' => 'chart',
                'active' => request()->routeIs('reports.*'),
                'children' => [
                    ['name' => 'Отчёт по закрытым заявкам', 'route' => 'reports.closed-orders'],
                    ['name' => 'Отчёт по городу', 'route' => 'reports.by-city'],
                    ['name' => 'Город + мастера', 'route' => 'reports.city-employee'],
                    ['name' => 'Помесячная таблица', 'route' => 'reports.monthly-table'],
                    ['name' => 'Оборот мастеров', 'route' => 'reports.masters-turnover'],
                    ['name' => 'Статистика заявок', 'route' => 'reports.orders'],
                    ['name' => 'Отмены и отказы', 'route' => 'reports.cancellations'],
                    ['name' => 'Партнёры', 'route' => 'reports.partners'],
                ],
            ];
        }

        // ОКК (Претензии)
        if ($this->canAccessComplaints($user)) {
            $children = [];

            $children[] = ['name' => 'Претензии', 'route' => 'complaints.index'];

            $children[] = ['name' => 'Отчёт', 'route' => 'complaints.report'];

            if ($user->hasAnyRole(['developer'])) {
                $children[] = ['name' => 'Обратная связь', 'route' => 'complaints.reviews'];
            }

            $menu[] = [
                'name' => 'ОКК',
                'icon' => 'warning',
                'active' => request()->routeIs('complaints.*'),
                'children' => $children,
            ];
        }

        // Управление (города + кассовые категории)
        if ($this->canAccessManagement($user)) {
            $menu[] = [
                'name' => 'Управление',
                'icon' => 'settings',
                'active' => request()->routeIs('management.*') || request()->routeIs('cfm.editor*'),
                'children' => [
                    ['name' => 'Города', 'route' => 'management.cities.index'],
                    ['name' => 'Макеты листовок', 'route' => 'management.flyer-makets.index'],
                    ['name' => 'Источники заказов', 'route' => 'management.sources.index'],
                    ['name' => 'Кассовые категории', 'route' => 'cfm.editor'],
                ],
            ];
        } elseif ($this->canAccessSourcesManagement($user)) {
            $menu[] = [
                'name' => 'Источники заказов',
                'route' => 'management.sources.index',
                'icon' => 'settings',
                'active' => request()->routeIs('management.sources.*'),
            ];
        }

        // Реклама - ВРЕМЕННО ОТКЛЮЧЕНО
        // if ($this->canAccessAds($user)) {
        //     $menu[] = [
        //         'name' => 'Реклама',
        //         'icon' => 'ads',
        //         'active' => request()->routeIs('prom.*'),
        //         'children' => [
        //             ['name' => 'Журнал', 'route' => 'prom.journal', 'icon' => 'calendar'],
        //             ['name' => 'Маршруты', 'route' => 'prom.routes', 'icon' => 'map'],
        //             ['name' => 'Разноска', 'route' => 'prom.actions', 'icon' => 'truck'],
        //             ['name' => 'Оплата', 'route' => 'prom.payments', 'icon' => 'wallet'],
        //         ],
        //     ];
        // }

        // База знаний — доступна всем
        $menu[] = [
            'name' => 'База знаний',
            'route' => 'information.index',
            'icon' => 'info',
            'active' => request()->routeIs('information.*'),
        ];

        return $menu;
    }

    // Методы проверки доступа к разделам

    private function canAccessDispatcherDashboard(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'call_center', 'senior_dispatcher',
            'regional_director', 'general_director',
        ]);
    }

    private function canAccessOrders(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'call_center', 'senior_dispatcher', 'senior_manager',
            'tech_director', 'branch_head', 'regional_director', 'general_director',
        ]);
    }

    private function canAccessPersons(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'call_center', 'senior_dispatcher',
        ]);
    }

    private function canAccessCfm(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'branch_head', 'regional_director', 'general_director',
            'senior_manager', 'call_center', 'senior_dispatcher',
        ]);
    }

    private function canAccessCfmSettlement(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'branch_head', 'regional_director', 'general_director', 'senior_manager',
        ]);
    }

    private function canAccessHr(User $user): bool
    {
        if (app(HrService::class)->isCallCenterSupervisor($user)) {
            return true;
        }

        return $user->hasAnyRole([
            'developer', 'senior_manager', 'tech_director',
            'branch_head', 'regional_director', 'general_director',
        ]);
    }

    private function canAccessHrList(User $user): bool
    {
        // Диспетчеры КЦ — без списка сотрудников
        if ($user->hasRole('call_center') && ! app(HrService::class)->isCallCenterSupervisor($user)
            && ! $user->hasAnyRole([
                'developer', 'general_director', 'senior_manager',
                'tech_director', 'branch_head', 'regional_director',
            ])) {
            return false;
        }

        return $this->canAccessHr($user);
    }

    private function canAccessHrMasters(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'senior_manager', 'tech_director',
            'branch_head', 'regional_director', 'general_director',
        ]);
    }

    /** Чёрный список в меню: не senior_manager и не tech_director (у маршрута нет tech_director). */
    private function canAccessHrBlacklistNav(User $user): bool
    {
        if ($user->hasRole('senior_manager') && ! $user->hasRole('developer')) {
            return false;
        }
        if ($user->hasRole('tech_director') && ! $user->hasRole('developer')) {
            return false;
        }

        return $user->hasAnyRole([
            'developer', 'branch_head', 'regional_director', 'general_director',
        ]);
    }

    /** График мастеров: не tech_director (кроме developer). */
    private function canAccessHrRosterNav(User $user): bool
    {
        if ($user->hasRole('tech_director') && ! $user->hasRole('developer')) {
            return false;
        }

        return true;
    }

    private function canAccessReports(User $user): bool
    {
        return $user->hasAnyRole([
            'developer',
            'branch_head', 'regional_director', 'general_director',
        ]);
    }

    private function canAccessComplaints(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'call_center', 'senior_dispatcher', 'senior_manager',
            'branch_head', 'regional_director', 'general_director',
        ]);
    }

    private function canAccessAds(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'senior_manager',
            'branch_head', 'regional_director',
        ]);
    }

    private function canAccessManagement(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'general_director',
        ]);
    }

    private function canAccessSourcesManagement(User $user): bool
    {
        return $user->hasRole('senior_dispatcher');
    }

    private function canAccessCityOpenTimes(User $user): bool
    {
        return $user->hasAnyRole([
            'developer', 'branch_head', 'regional_director', 'general_director', 'tech_director',
        ]);
    }
}
