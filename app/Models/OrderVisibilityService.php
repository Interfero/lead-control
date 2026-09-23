<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

/**
 * Единое место правил видимости заказов по ролям (список, счётчик, легенда, селекты).
 */
class OrderVisibilityService
{
    public function getVisibleStatuses(User $user): array
    {
        if ($user->hasRole('developer') || $user->hasRole('general_director')) {
            return Order::allStatusCodes();
        }

        if ($user->hasRole('senior_dispatcher')) {
            return Order::allStatusCodes();
        }

        if ($user->hasRole('call_center')) {
            // КЦ видит свои статусы + активные заявки филиала (для сортировки и контроля)
            return [
                'callback', 'not_processed', 'pending',
                'on_way', 'in_progress', 'in_progress_sd', 'rejected',
                'completed', 'cancelled_cc', 'cancelled_city',
            ];
        }

        // Менеджер филиала, руководитель, тех., рег. директор
        return [
            'pending',
            'on_way',
            'in_progress',
            'in_progress_sd',
            'review',
            'completed',
            'rejected',
            'cancelled_cc',
            'cancelled_city',
        ];
    }

    /**
     * Статусы, которые роль может выставить вручную.
     */
    public function getSettableStatuses(User $user): array
    {
        if ($user->hasRole('developer')) {
            return Order::allStatusCodes();
        }

        if ($user->hasRole('general_director')) {
            return Order::allStatusCodes();
        }

        if ($user->hasRole('call_center')) {
            return ['callback', 'not_processed', 'pending', 'cancelled_cc', 'cancelled_city'];
        }

        if ($user->hasRole('senior_dispatcher')) {
            return [
                'callback', 'not_processed', 'pending', 'on_way', 'in_progress', 'in_progress_sd',
                'review',
                'completed', 'rejected', 'cancelled_cc', 'cancelled_city',
            ];
        }

        return ['pending', 'on_way', 'in_progress', 'in_progress_sd', 'review', 'completed', 'rejected'];
    }

    /**
     * Элементы легенды списка заявок (ТЗ: разный набор для КЦ / города / ген. директора).
     */
    public function getLegendItemsForOrders(User $user): array
    {
        $statusLabels = Order::getStatusLabels();
        $items = [];

        if ($user->hasRole('call_center') && ! $user->hasRole('developer')) {
            $items[] = [
                'text' => 'П — просмотрено филиалом (галочка, если карточку открывал старший менеджер или руководитель филиала)',
            ];
            $ccOrder = ['pending', 'on_way', 'in_progress', 'in_progress_sd', 'completed', 'rejected', 'callback', 'not_processed', 'cancelled_cc', 'cancelled_city'];
            foreach ($ccOrder as $code) {
                $items[] = [
                    'label' => $statusLabels[$code] ?? $code,
                    'badge' => $code,
                ];
            }
            $items[] = [
                'text' => 'Готов, Отказ и отмены в списке — '.Order::DISPATCHER_ARCHIVE_DAYS.' дней после закрытия, затем скрываются',
                'legend_class' => 'legend-footnote',
            ];

            return $this->appendOrderListMetaLegend($items);
        }

        if ($user->hasRole('developer') || $user->hasRole('general_director')) {
            $items[] = [
                'text' => 'Время встречи просрочено, мигает (кроме «В работе»)',
                'legend_class' => 'legend-blink',
                'indicator' => true,
            ];
            $items[] = [
                'text' => 'П — просмотрено филиалом (галочка, если карточку открывал старший менеджер или руководитель филиала)',
            ];
            foreach (Order::allStatusCodes() as $code) {
                $items[] = [
                    'label' => $statusLabels[$code] ?? $code,
                    'badge' => $code,
                ];
            }

            return $this->appendOrderListMetaLegend($items);
        }

        // Город (менеджер, руководитель, тех., рег. директор)
        $items[] = [
            'text' => 'Время встречи просрочено, мигает (кроме «В работе»)',
            'legend_class' => 'legend-blink',
            'indicator' => true,
        ];
        $items[] = [
            'text' => 'П — просмотрено филиалом (галочка, если карточку открывал старший менеджер или руководитель филиала)',
        ];

        $cityOrder = [
            'pending',
            'on_way',
            'in_progress',
            'in_progress_sd',
            'review',
            'completed',
            'rejected',
            'cancelled_cc',
            'cancelled_city',
        ];

        foreach ($cityOrder as $code) {
            $suffix = in_array($code, ['cancelled_cc', 'cancelled_city'], true) ? '*' : '';
            $items[] = [
                'label' => ($statusLabels[$code] ?? $code).$suffix,
                'badge' => $code,
            ];
        }

        $items[] = [
            'text' => '* Филиал не назначает этот статус; отображается, если его выставил КЦ.',
            'legend_class' => 'legend-footnote',
        ];

        return $this->appendOrderListMetaLegend($items);
    }

    /** Тип заказа — общая часть легенды списка. */
    private function appendOrderListMetaLegend(array $items): array
    {
        $items[] = ['text' => 'Тип заказа', 'legend_class' => 'legend-footnote'];
        $items[] = ['color' => '#7a4a00', 'text' => 'Повтор'];
        $items[] = ['color' => '#ffaa00', 'text' => 'Гарантия'];

        return $items;
    }

    /** Статусы, доступные при создании заказа (КЦ / разработчик). */
    public function getAllowedStatusesForOrderCreate(User $user): array
    {
        if ($user->hasRole('developer')) {
            return Order::allStatusCodes();
        }

        return ['callback', 'not_processed', 'pending', 'cancelled_cc', 'cancelled_city'];
    }

    /** Диспетчеры: «Готов»/«Отказ»/отмены видны в списке ограниченное время после закрытия. */
    public function usesDispatcherArchiveWindow(User $user): bool
    {
        return $user->isDispatcher() && ! $user->hasRole('developer');
    }
}
