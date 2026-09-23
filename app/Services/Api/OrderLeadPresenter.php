<?php

namespace App\Services\Api;

use App\Models\Order;
use App\Support\OrderLeadSource;

/**
 * Единый контракт «заявка» для SuperPart / Desk / внешних интеграций.
 */
class OrderLeadPresenter
{
    /**
     * Полное тело заявки (create/get/list).
     *
     * @return array<string, mixed>
     */
    public function present(Order $order): array
    {
        $order->loadMissing(['address.city', 'persons.phones', 'master', 'source']);

        $city = $order->address?->city;
        $person = $order->persons->first();
        $phone = $person?->phones->first()?->phone_number;

        $addressParts = [];
        if ($city?->city_name) {
            $addressParts[] = 'г. '.$city->city_name;
        }
        if ($order->address) {
            $addressParts[] = $order->address->full_address;
        }

        $status = (string) $order->order_status;
        $labels = Order::getStatusLabels();

        return [
            'order_id' => (int) $order->order_id,
            'external_id' => (string) $order->order_id,
            'source' => OrderLeadSource::LC,
            'city_id' => $order->address?->city_id ? (int) $order->address->city_id : null,
            'city_name' => $city?->city_name,
            'order_status' => $status,
            'status_label' => $labels[$status] ?? $status,
            'raw_status' => $status,
            'client_name' => $person?->person_name,
            'client_phone' => $phone,
            'phone' => $phone,
            'address' => implode(', ', array_filter($addressParts)),
            'street' => $order->address?->street,
            'house' => $order->address?->house,
            'flat' => $order->address?->flat,
            'address_adds' => $order->address?->address_adds,
            'datetime_order' => $order->datetime_order?->toIso8601String(),
            'call_at_local' => optional($order->datetime_order)?->toDateTimeString(),
            'order_created_at' => $order->order_created_at?->toIso8601String(),
            'order_closed_at' => $order->order_closed_at?->toIso8601String(),
            'created_at_local' => optional($order->order_created_at)?->toDateTimeString(),
            'timezone' => $city?->city_timezone,
            'partner_user_id' => $order->partner_user_id ? (int) $order->partner_user_id : null,
            'source_id' => $order->source_id ? (int) $order->source_id : null,
            'marketing_source_name' => $order->source?->source_name,
            'order_core' => $order->order_core,
            'equipment_type' => $order->equipment_type,
            'order_adds' => $order->order_adds,
            'description' => $order->order_adds,
            'master_id' => $order->master_id ? (int) $order->master_id : null,
            'master_name' => $order->master?->user_name,
            'paid_amount' => $order->amount_paid,
            'parts_amount' => $order->amount_comp,
            'order_type' => match ($order->order_type) {
                'repeat' => 'repeat',
                'warranty' => 'warranty',
                default => 'first',
            },
            'is_closed' => $order->isClosedStatus(),
        ];
    }

    /**
     * Компактный ответ по статусу.
     *
     * @return array<string, mixed>
     */
    public function presentStatus(Order $order): array
    {
        $status = (string) $order->order_status;
        $labels = Order::getStatusLabels();

        return [
            'order_id' => (int) $order->order_id,
            'external_id' => (string) $order->order_id,
            'source' => OrderLeadSource::LC,
            'order_status' => $status,
            'status_label' => $labels[$status] ?? $status,
            'raw_status' => $status,
            'is_closed' => $order->isClosedStatus(),
            'order_closed_at' => $order->order_closed_at?->toIso8601String(),
            'city_id' => $order->address?->city_id ? (int) $order->address->city_id : null,
        ];
    }

    /**
     * Словарь статусов для API.
     *
     * @return list<array{code: string, label: string, is_closed: bool}>
     */
    public function statusCatalog(): array
    {
        $closed = ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'];
        $out = [];
        foreach (Order::getStatusLabels() as $code => $label) {
            // не отдаём устаревшие waiting_* в каталоге для новых интеграций
            if (str_starts_with($code, 'waiting_')) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => $label,
                'is_closed' => in_array($code, $closed, true),
            ];
        }

        return $out;
    }
}
