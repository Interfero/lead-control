<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Address;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonPhone;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PartnerOrderIngestService
{
    /**
     * Создание заказа из JSON SuperPart (после валидации HTTP-слоя).
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrder(array $data, ?string $idempotencyKey): Order
    {
        $authorId = $this->resolveAuthorUserId();

        $explicitSourceId = 0;
        if (isset($data['source_id']) && is_numeric($data['source_id']) && (int) $data['source_id'] > 0) {
            $explicitSourceId = (int) $data['source_id'];
        }

        $sourceId = $explicitSourceId > 0
            ? $explicitSourceId
            : $this->resolveDefaultSourceId();

        if ($authorId < 1) {
            throw ValidationException::withMessages([
                'config' => ['В CRM нет пользователя для автора заявки из SuperPart. Задайте SUPERPART_ORDER_AUTHOR_USER_ID.'],
            ]);
        }

        if ($sourceId < 1) {
            throw ValidationException::withMessages([
                'config' => ['Укажите SUPERPART_DEFAULT_SOURCE_ID (положительное число в .env) или передайте source_id в запросе.'],
            ]);
        }

        $source = Source::query()->find($sourceId);
        if (! $source) {
            throw ValidationException::withMessages([
                'config' => ["В CRM нет источника с source_id={$sourceId}. Задайте в .env SUPERPART_DEFAULT_SOURCE_ID на существующий source_id из таблицы sources."],
            ]);
        }

        if (! $source->is_active) {
            throw ValidationException::withMessages([
                'config' => ["Источник source_id={$sourceId} отключён (is_active=0). Включите источник в CRM или укажите другой источник."],
            ]);
        }

        if ($explicitSourceId > 0) {
            if (! $source->available_for_superpart) {
                throw ValidationException::withMessages([
                    'source_id' => ['Источник не включён в каталог SuperPart (available_for_superpart).'],
                ]);
            }
            $partnerUserId = (int) $data['partner_user_id'];
            if ($source->superpart_partner_id !== null && (int) $source->superpart_partner_id !== $partnerUserId) {
                throw ValidationException::withMessages([
                    'source_id' => ['Этот источник закреплён за другим партнёром в SuperPart.'],
                ]);
            }
        }

        $phoneNorm = PhoneHelper::normalize($data['client_phone'] ?? '');
        if ($phoneNorm === '' || ! PhoneHelper::isValid($data['client_phone'] ?? '')) {
            throw ValidationException::withMessages([
                'client_phone' => ['Некорректный телефон клиента.'],
            ]);
        }

        $phoneLock = 'person_phone_'.$phoneNorm;
        $usePhoneLock = DB::getDriverName() === 'mysql';
        if ($usePhoneLock) {
            $acquiredPhone = DB::selectOne('SELECT GET_LOCK(?, 30) as v', [$phoneLock]);
            if (! $acquiredPhone || (int) $acquiredPhone->v !== 1) {
                throw ValidationException::withMessages([
                    'client_phone' => ['Не удалось получить блокировку по телефону клиента.'],
                ]);
            }
        }

        $lockName = null;
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $lockName = 'sp_po_'.substr(hash('sha256', $idempotencyKey), 0, 56);
            if ($usePhoneLock) {
                $acquired = DB::selectOne('SELECT GET_LOCK(?, 30) as v', [$lockName]);
                if (! $acquired || (int) $acquired->v !== 1) {
                    DB::select('SELECT RELEASE_LOCK(?) as v', [$phoneLock]);
                    throw ValidationException::withMessages([
                        'idempotency' => ['Не удалось получить блокировку для Idempotency-Key.'],
                    ]);
                }
            }
        }

        try {
            return DB::transaction(function () use ($data, $idempotencyKey, $authorId, $sourceId, $phoneNorm) {
                if ($idempotencyKey !== null && $idempotencyKey !== '') {
                    $existing = DB::table('partner_order_idempotency')
                        ->where('idempotency_key', $idempotencyKey)
                        ->value('order_id');
                    if ($existing) {
                        return Order::query()->findOrFail($existing);
                    }
                }

                $person = Person::query()
                    ->whereHas('phones', fn ($q) => $q->where('phone_number', $phoneNorm))
                    ->first();

                if (! $person) {
                    $person = Person::create([
                        'person_name' => $data['client_name'],
                    ]);
                    PersonPhone::create([
                        'person_id' => $person->person_id,
                        'phone_number' => $phoneNorm,
                        'phone_adds' => null,
                    ]);
                } else {
                    if (! empty($data['client_name']) && $person->person_name !== $data['client_name']) {
                        $person->person_name = $data['client_name'];
                        $person->save();
                    }
                }

                $address = Address::create([
                    'person_id' => $person->person_id,
                    'city_id' => $data['city_id'],
                    'street' => $data['street'],
                    'house' => $data['house'],
                    'flat' => $data['flat'] ?? null,
                    'address_adds' => $data['address_adds'] ?? null,
                ]);

                $orderCore = $data['order_core'] ?? 'core';
                if (! empty($data['equipment_type'])) {
                    $orderCore = Order::getOrderCoreByEquipment($data['equipment_type']);
                }

                $order = new Order([
                    'address_id' => $address->address_id,
                    'datetime_order' => $data['datetime_order'],
                    'order_type' => 'new',
                    'order_core' => $orderCore,
                    'equipment_type' => $data['equipment_type'] ?? null,
                    'order_status' => 'pending',
                    'order_adds' => $data['order_adds'] ?? null,
                    'source_id' => $sourceId,
                    'order_created_at' => now(),
                    'master_id' => null,
                    'amount_paid' => 0,
                    'amount_comp' => 0,
                    'partner_user_id' => $data['partner_user_id'],
                ]);
                $order->order_created_by = $authorId;
                $order->save();

                $order->persons()->attach($person->person_id);

                if ($idempotencyKey !== null && $idempotencyKey !== '') {
                    DB::table('partner_order_idempotency')->insert([
                        'idempotency_key' => $idempotencyKey,
                        'order_id' => $order->order_id,
                        'created_at' => now(),
                    ]);
                }

                return $order;
            });
        } finally {
            if ($usePhoneLock && $lockName !== null) {
                DB::select('SELECT RELEASE_LOCK(?) as v', [$lockName]);
            }
            if ($usePhoneLock) {
                DB::select('SELECT RELEASE_LOCK(?) as v', [$phoneLock]);
            }
        }
    }

    private function resolveAuthorUserId(): int
    {
        $configured = (int) config('services.superpart.order_author_user_id');
        if ($configured > 0 && User::query()->where('user_id', $configured)->exists()) {
            return $configured;
        }

        $fromRecent = (int) (DB::table('orders')
            ->whereNotNull('order_created_by')
            ->orderByDesc('order_id')
            ->value('order_created_by') ?? 0);
        if ($fromRecent > 0 && User::query()->where('user_id', $fromRecent)->exists()) {
            Log::warning('PartnerOrderIngest: SUPERPART_ORDER_AUTHOR_USER_ID missing, using recent order_created_by', [
                'configured' => $configured,
                'fallback' => $fromRecent,
            ]);

            return $fromRecent;
        }

        $active = (int) (User::query()->where('is_active', true)->orderBy('user_id')->value('user_id') ?? 0);
        if ($active > 0) {
            Log::warning('PartnerOrderIngest: SUPERPART_ORDER_AUTHOR_USER_ID missing, using first active user', [
                'configured' => $configured,
                'fallback' => $active,
            ]);
        }

        return $active;
    }

    private function resolveDefaultSourceId(): int
    {
        $configured = (int) config('services.superpart.default_source_id');
        $configuredRow = $configured > 0
            ? Source::query()->where('source_id', $configured)->first()
            : null;
        if ($configuredRow && $configuredRow->is_active && $configuredRow->available_for_superpart) {
            return $configured;
        }

        $fallback = (int) (Source::query()
            ->where('is_active', true)
            ->where('available_for_superpart', true)
            ->orderBy('source_id')
            ->value('source_id') ?? 0);
        if ($fallback > 0) {
            Log::warning('PartnerOrderIngest: SUPERPART_DEFAULT_SOURCE_ID unusable, using fallback', [
                'configured' => $configured,
                'fallback' => $fallback,
            ]);
        }

        return $fallback;
    }
}
