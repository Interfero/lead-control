<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

/**
 * Единое место правил видимости заказов по ролям (список, счётчик, легенда, селекты).
 * Сверка с PDF тестера: статусы КЦ / города / разраб+ген.дир.
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
            // КЦ: свои статусы + активные «городские» (СД / в пути / в работе / ожидание)
            // + закрытые для архива 7 дней
            return array_values(array_unique(array_merge(
                Order::dispatcherActiveStatusCodes(),
                Order::dispatcherArchiveStatusCodes(),
            )));
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
        if ($user->hasRole('developer') || $user->hasRole('general_director')) {
            return Order::allStatusCodes();
        }

        if ($user->hasRole('call_center')) {
            return ['callback', 'not_processed', 'pending', 'cancelled_cc', 'cancelled_city'];
        }

        if ($user->hasRole('senior_dispatcher')) {
            return Order::allStatusCodes();
        }

        // Город не ставит отмены КЦ/город — только видит
        return [
            'pending',
            'on_way',
            'in_progress',
            'in_progress_sd',
            'review',
            'completed',
            'rejected',
        ];
    }

    /**
     * Элементы легенды списка заявок (ТЗ: разный набор для КЦ / города / ген. директора).
     */
    public function getLegendItemsForOrders(User $user): array
    {
        $statusLabels = Order::getStatusLabels();
        $items = [];

        if ($user->hasRole('call_center') && ! $user->hasRole('developer')) {
            $ccOrder = [
                'callback',
                'on_way',
                'in_progress',
                'in_progress_sd',
                'pending',
                'review',
                'not_processed',
                'cancelled_cc',
                'cancelled_city',
                'completed',
                'rejected',
            ];
            foreach ($ccOrder as $code) {
                $items[] = [
                    'label' => $statusLabels[$code] ?? $code,
                    'badge' => $code,
                ];
            }
            $items[] = [
                'text' => 'Отмены/Готов/Отказ в списке — '.Order::DISPATCHER_ARCHIVE_DAYS.' дней после закрытия, затем скрываются (поиск и «Закрытые» — отдельно)',
                'legend_class' => 'legend-footnote',
            ];

            return $this->appendOrderListMetaLegend($items);
        }

        if ($user->hasRole('developer') || $user->hasRole('general_director')) {
            $items[] = [
                'text' => 'Время заявки мигает красным только в статусе «Ожидание», если до встречи меньше часа (по часовому поясу города)',
                'legend_class' => 'legend-blink',
                'indicator' => true,
            ];
            $items[] = [
                'text' => 'П — просмотрено филиалом (или заказ уже в работе / закрыт)',
            ];
            foreach (Order::allStatusCodes() as $code) {
                $items[] = [
                    'label' => $statusLabels[$code] ?? $code,
                    'badge' => $code,
                ];
            }

            return $this->appendOrderListMetaLegend($items);
        }

        $items[] = [
            'text' => 'Время заявки мигает красным только в статусе «Ожидание», если до встречи меньше часа (по часовому поясу города)',
            'legend_class' => 'legend-blink',
            'indicator' => true,
        ];
        $items[] = [
            'text' => 'П — просмотрено филиалом (или заказ уже в работе / закрыт)',
        ];

        $cityOrder = [
            'pending',
            'on_way',
            'in_progress',
            'in_progress_sd',
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

        // КЦ при создании: Прозвон / Не оформлена / Ожидание
        return ['callback', 'not_processed', 'pending'];
    }

    /** Диспетчеры: «Готов»/«Отказ»/отмены видны в списке ограниченное время после закрытия. */
    public function usesDispatcherArchiveWindow(User $user): bool
    {
        return $user->isDispatcher() && ! $user->hasRole('developer');
    }
}
