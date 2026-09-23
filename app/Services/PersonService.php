<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Address;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonPhone;
use Illuminate\Support\Facades\DB;

class PersonService
{
    /**
     * Поиск персон по ID заказа, телефону и/или адресу
     */
    public function search(?string $orderId, ?string $phone, ?string $address): array
    {
        if ($orderId !== null && $orderId !== '' && ctype_digit((string) $orderId) && (int) $orderId === 0) {
            return [];
        }

        // Если ищут только по ID заказа, используем специальную логику
        if ($orderId && !$phone && !$address) {
            return $this->searchByOrderId((int) $orderId);
        }
        
        $with = ['phones', 'addresses.city'];
        $cleanPhone = $this->normalizeSearchPhone($phone);
        if ($cleanPhone !== null && $orderId === null) {
            $with[] = 'orders.address.city';
        }

        $query = Person::query()->with($with);
        
        // Поиск по ID заказа (в комбинации с другими параметрами)
        if ($orderId) {
            $orderIdInt = (int) $orderId;
            if ($orderIdInt > 0) {
                $query->whereHas('orders', function ($q) use ($orderIdInt) {
                    $q->where('orders.order_id', $orderIdInt);
                });
            }
        }
        
        if ($phone) {
            if ($cleanPhone !== null) {
                $query->whereHas('phones', fn ($q) => $q->where('phone_number', $cleanPhone));
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        
        // Если есть поиск по адресу, загружаем больше данных для фильтрации в PHP
        if ($address) {
            $query->whereHas('addresses');
        }
        
        $persons = $query->limit(200)->get();

        $addressParts = $address ? preg_split('/\s+/', mb_strtolower(trim($address), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) : [];
        if ($cleanPhone !== null && ! $orderId) {
            return $this->collapsePhoneSearch($persons, is_array($addressParts) ? $addressParts : []);
        }
        
        // Форматируем результат — одна строка на каждый адрес
        $results = [];
        
        // Если ищут по ID заказа, получаем адрес заказа для фильтрации
        $orderAddressId = null;
        if ($orderId) {
            $order = \App\Models\Order::find((int) $orderId);
            if ($order && $order->address_id) {
                $orderAddressId = $order->address_id;
            }
        }
        
        foreach ($persons as $person) {
            foreach ($person->addresses as $addr) {
                // Если ищут по ID заказа, показываем только адрес этого заказа
                if ($orderAddressId && $addr->address_id !== $orderAddressId) {
                    continue;
                }
                
                // Если есть поиск по адресу, проверяем совпадение
                if (! empty($addressParts) && ! $this->addressMatches($addr, $addressParts)) {
                    continue;
                }
                
                $results[] = [
                    'person_id' => $person->person_id,
                    'person_name' => $person->person_name,
                    'person_age' => $person->person_age,
                    'order_id' => $orderId ? (int) $orderId : null,
                    'address_id' => $addr->address_id,
                    'city_name' => $addr->city?->city_name ?? '',
                    'address' => "{$addr->street}, {$addr->house}" . ($addr->flat ? ", кв. {$addr->flat}" : ''),
                    'address_adds' => $addr->address_adds,
                    // Телефоны в JSON поиска не отдаём — UI их не показывает, а боты скрейпят именно их.
                    'phones_count' => $person->phones->count(),
                ];
                
                // Ограничиваем до 50 результатов
                if (count($results) >= 50) {
                    break 2;
                }
            }
        }
        
        return $results;
    }

    protected function normalizeSearchPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $cleanPhone = PhoneHelper::normalize($phone);
        if (strlen($cleanPhone) === 10 && ctype_digit($cleanPhone)) {
            return $cleanPhone;
        }

        return null;
    }

    /**
     * @param  list<string>  $addressParts
     */
    protected function addressMatches(Address $addr, array $addressParts): bool
    {
        if ($addressParts === []) {
            return true;
        }
        $streetLower = mb_strtolower($addr->street ?? '', 'UTF-8');
        $houseLower = mb_strtolower($addr->house ?? '', 'UTF-8');
        $flatLower = mb_strtolower($addr->flat ?? '', 'UTF-8');
        foreach ($addressParts as $part) {
            $found = mb_strpos($streetLower, $part) !== false
                || mb_strpos($houseLower, $part) !== false
                || mb_strpos($flatLower, $part) !== false;
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Один номер → одна строка: клиент и заявка с самым поздним визитом.
     *
     * @param  \Illuminate\Support\Collection<int, Person>  $persons
     * @param  list<string>  $addressParts
     * @return list<array<string, mixed>>
     */
    protected function collapsePhoneSearch($persons, array $addressParts): array
    {
        $matchedPersons = [];
        foreach ($persons as $person) {
            $hasMatch = false;
            foreach ($person->addresses as $addr) {
                if ($this->addressMatches($addr, $addressParts)) {
                    $hasMatch = true;
                    break;
                }
            }
            if ($addressParts === [] || $hasMatch) {
                $matchedPersons[] = $person;
            }
        }
        if ($matchedPersons === []) {
            return [];
        }

        $bestOrder = null;
        $bestPerson = null;
        foreach ($matchedPersons as $person) {
            foreach ($person->orders as $order) {
                if ($addressParts !== []) {
                    $orderAddr = $order->address;
                    if (! $orderAddr || ! $this->addressMatches($orderAddr, $addressParts)) {
                        continue;
                    }
                }
                if ($this->isLaterVisit($order, $bestOrder)) {
                    $bestOrder = $order;
                    $bestPerson = $person;
                }
            }
        }

        if ($bestOrder && $bestPerson) {
            $addr = $bestOrder->address;
            if (! $addr) {
                $addr = $bestPerson->addresses->first(fn ($a) => $this->addressMatches($a, $addressParts))
                    ?? $bestPerson->addresses->first();
            }
            if (! $addr) {
                return [];
            }

            return [$this->resultRow($bestPerson, $addr, (int) $bestOrder->order_id)];
        }

        $person = collect($matchedPersons)->sortBy('person_id')->first();
        $addr = $person->addresses->first(fn ($a) => $this->addressMatches($a, $addressParts))
            ?? $person->addresses->first();
        if (! $addr) {
            return [];
        }

        return [$this->resultRow($person, $addr, null)];
    }

    protected function isLaterVisit(Order $candidate, ?Order $current): bool
    {
        if ($current === null) {
            return true;
        }
        $a = $candidate->datetime_order;
        $b = $current->datetime_order;
        if ($a && $b && $a->ne($b)) {
            return $a->gt($b);
        }
        if ($a && ! $b) {
            return true;
        }
        if (! $a && $b) {
            return false;
        }

        return (int) $candidate->order_id > (int) $current->order_id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultRow(Person $person, Address $addr, ?int $orderId): array
    {
        return [
            'person_id' => $person->person_id,
            'person_name' => $person->person_name,
            'person_age' => $person->person_age,
            'order_id' => $orderId,
            'address_id' => $addr->address_id,
            'city_name' => $addr->city?->city_name ?? '',
            'address' => "{$addr->street}, {$addr->house}".($addr->flat ? ", кв. {$addr->flat}" : ''),
            'address_adds' => $addr->address_adds,
            'phones_count' => $person->phones->count(),
        ];
    }
    
    /**
     * Поиск персон только по ID заказа (точный поиск)
     */
    private function searchByOrderId(int $orderId): array
    {
        $order = \App\Models\Order::with(['persons.phones', 'persons.addresses.city', 'address'])->find($orderId);
        
        if (!$order) {
            return [];
        }
        
        $results = [];
        $orderAddressId = $order->address_id;
        
        // Получаем всех персон, связанных с заказом
        foreach ($order->persons as $person) {
            $addressFound = false;
            
            // Сначала пытаемся найти адрес, совпадающий с адресом заказа
            foreach ($person->addresses as $addr) {
                if ($addr->address_id === $orderAddressId) {
                    $results[] = [
                        'person_id' => $person->person_id,
                        'person_name' => $person->person_name,
                        'person_age' => $person->person_age,
                        'order_id' => $order->order_id,
                        'address_id' => $addr->address_id,
                        'city_name' => $addr->city?->city_name ?? '',
                        'address' => "{$addr->street}, {$addr->house}" . ($addr->flat ? ", кв. {$addr->flat}" : ''),
                        'address_adds' => $addr->address_adds,
                        // Телефоны в JSON поиска не отдаём — UI их не показывает, а боты скрейпят именно их.
                        'phones_count' => $person->phones->count(),
                    ];
                    $addressFound = true;
                    break; // Показываем только один адрес (адрес заказа)
                }
            }
            
            // Если адрес заказа не найден, но персона связана с заказом, показываем первого адреса персоны
            if (!$addressFound && $person->addresses->isNotEmpty()) {
                $addr = $person->addresses->first();
                $results[] = [
                    'person_id' => $person->person_id,
                    'person_name' => $person->person_name,
                    'person_age' => $person->person_age,
                    'order_id' => $order->order_id,
                    'address_id' => $addr->address_id,
                    'city_name' => $addr->city?->city_name ?? '',
                    'address' => "{$addr->street}, {$addr->house}" . ($addr->flat ? ", кв. {$addr->flat}" : ''),
                    'address_adds' => $addr->address_adds,
                    // Телефоны в JSON поиска не отдаём — UI их не показывает, а боты скрейпят именно их.
                    'phones_count' => $person->phones->count(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Создание персоны с телефоном и адресом
     */
    public function create(array $data): Person
    {
        $phone = PhoneHelper::normalize((string) ($data['phone_number'] ?? ''));
        if (strlen($phone) !== 10) {
            $phone = preg_replace('/\D/', '', (string) ($data['phone_number'] ?? '')) ?? '';
            $phone = substr($phone, -10);
        }

        $lockName = strlen($phone) === 10 ? 'person_phone_'.$phone : null;
        $useLock = $lockName !== null && DB::getDriverName() === 'mysql';
        if ($useLock) {
            $acquired = DB::selectOne('SELECT GET_LOCK(?, 10) as v', [$lockName]);
            if (! $acquired || (int) $acquired->v !== 1) {
                throw new \RuntimeException('Не удалось создать клиента: номер уже обрабатывается');
            }
        }

        try {
            return DB::transaction(function () use ($data, $phone) {
                $existingPhone = strlen($phone) === 10
                    ? PersonPhone::query()->where('phone_number', $phone)->orderBy('phone_id')->first()
                    : null;

                if ($existingPhone) {
                    $person = Person::query()->findOrFail($existingPhone->person_id);
                    if (! empty($data['person_name'])) {
                        $person->person_name = $data['person_name'];
                    }
                    if (array_key_exists('person_age', $data) && $data['person_age'] !== null && $data['person_age'] !== '') {
                        $person->person_age = $data['person_age'];
                    }
                    $person->save();
                } else {
                    $person = Person::create([
                        'person_name' => $data['person_name'],
                        'person_age' => $data['person_age'] ?? null,
                    ]);
                    $person->phones()->create([
                        'phone_number' => $phone !== '' ? $phone : $data['phone_number'],
                    ]);
                }

                $street = $data['street'] ?? null;
                $house = $data['house'] ?? null;
                $flat = $data['flat'] ?? null;
                $same = $person->addresses()
                    ->where('city_id', $data['city_id'])
                    ->where('street', $street)
                    ->where('house', $house)
                    ->where('flat', $flat)
                    ->first();
                if (! $same) {
                    $person->addresses()->create([
                        'city_id' => $data['city_id'],
                        'street' => $street,
                        'house' => $house,
                        'flat' => $flat,
                        'address_adds' => $data['address_adds'] ?? null,
                    ]);
                }

                return $person->load(['phones', 'addresses.city']);
            });
        } finally {
            if ($useLock) {
                DB::select('SELECT RELEASE_LOCK(?) as v', [$lockName]);
            }
        }
    }
}
