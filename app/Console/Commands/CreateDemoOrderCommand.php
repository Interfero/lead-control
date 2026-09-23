<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonPhone;
use App\Models\Source;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Создание полной тестовой заявки из консоли (SSH на сервере).
 * По умолчанию — город Тестоград (city_id=49), автор заявки user_id=131 (см. TestogradCliOrderAuthorSeeder).
 * Каждый запуск создаёт нового клиента, новый адрес и новый заказ.
 */
class CreateDemoOrderCommand extends Command
{
    /** Тестоград на проде/стенде */
    private const DEFAULT_CITY_ID = 49;

    /** Учётка «с лица» для order_created_by (создаётся сидером при отсутствии) */
    private const DEFAULT_CREATOR_USER_ID = 131;

    protected $signature = 'order:create-demo
                            {--city=49 : ID города (по умолчанию 49 — Тестоград)}
                            {--street=Тестовая улица : Улица}
                            {--house=123 : Номер дома}
                            {--flat= : Квартира (необязательно)}
                            {--creator=131 : user_id автора заказа (order_created_by); по умолчанию 131 — тестовый пользователь для CLI}
                            {--source=6 : source_id; иначе первый активный источник выбранного города}
                            {--status=pending : Код статуса заказа (как при создании из формы КЦ)}
                            {--type=new : Тип: new, repeat, warranty}
                            {--equipment=computer : Код вида техники (ключ Order::EQUIPMENT_TYPES)}';

    protected $description = 'Создать новую полную тестовую заявку в Тестограде (по умолчанию city=49, автор user=131). Каждый запуск — новая уникальная заявка.';

    public function handle(): int
    {
        $equipment = (string) $this->option('equipment');
        if (! array_key_exists($equipment, Order::EQUIPMENT_TYPES)) {
            $this->error('Некорректный --equipment. Допустимые ключи: '.implode(', ', array_keys(Order::EQUIPMENT_TYPES)));

            return self::FAILURE;
        }

        $orderType = (string) $this->option('type');
        if (! in_array($orderType, ['new', 'repeat', 'warranty'], true)) {
            $this->error('Некорректный --type. Допустимо: new, repeat, warranty');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');
        $allowedStatuses = Order::allStatusCodes();
        if (! in_array($status, $allowedStatuses, true)) {
            $this->error('Некорректный --status. Допустимые коды: '.implode(', ', $allowedStatuses));

            return self::FAILURE;
        }

        $city = $this->resolveCity();
        if (! $city) {
            $this->error('Не найден город city_id='.$this->option('city').'. Для Тестограда нужен city_id='.self::DEFAULT_CITY_ID.'.');

            return self::FAILURE;
        }

        $creator = $this->resolveCreator();
        if (! $creator) {
            $this->error('Не найден пользователь user_id='.$this->creatorUserId().' для order_created_by. Выполните: php artisan db:seed --class=TestogradCliOrderAuthorSeeder');

            return self::FAILURE;
        }

        $source = $this->resolveSource($city->city_id);
        if (! $source) {
            $this->error('Нет активного источника для города «'.$city->city_name.'». Создайте источник в CRM или укажите --source=<source_id>.');

            return self::FAILURE;
        }

        $street = (string) $this->option('street');
        $house = (string) $this->option('house');
        $flat = $this->option('flat') !== null && $this->option('flat') !== ''
            ? (string) $this->option('flat')
            : null;

        $uniq = (string) now()->format('YmdHis').'_'.bin2hex(random_bytes(3));
        $personName = 'Тестоград CLI '.$uniq;
        $phoneDigits = $this->uniquePhoneDigits();

        $orderCore = Order::getOrderCoreByEquipment($equipment);

        try {
            $orderId = DB::transaction(function () use (
                $city,
                $creator,
                $source,
                $personName,
                $phoneDigits,
                $street,
                $house,
                $flat,
                $orderType,
                $orderCore,
                $equipment,
                $status,
                $uniq
            ) {
                $person = Person::create([
                    'person_name' => $personName,
                    'person_age' => null,
                ]);

                PersonPhone::create([
                    'person_id' => $person->person_id,
                    'phone_number' => $phoneDigits,
                    'phone_adds' => 'Автотест order:create-demo (Тестоград)',
                ]);

                $address = Address::create([
                    'person_id' => $person->person_id,
                    'city_id' => $city->city_id,
                    'street' => $street,
                    'house' => $house,
                    'flat' => $flat,
                    'address_adds' => null,
                ]);

                $order = new Order([
                    'address_id' => $address->address_id,
                    'datetime_order' => now()->addDay(),
                    'order_status' => $status,
                    'order_type' => $orderType,
                    'order_core' => $orderCore,
                    'equipment_type' => $equipment,
                    'amount_paid' => 0,
                    'amount_comp' => 0,
                    'master_id' => null,
                    'source_id' => $source->source_id,
                    'order_adds' => 'Тестоград: тестовая заявка (консоль), метка '.$uniq.'. Описание по правилам формы.',
                    'order_created_at' => now(),
                ]);
                $order->order_created_by = $creator->user_id;
                $order->save();

                $order->persons()->attach($person->person_id);

                return $order->order_id;
            });
        } catch (\Throwable $e) {
            $this->error('Ошибка: '.$e->getMessage());

            return self::FAILURE;
        }

        $order = Order::query()->with(['source', 'address.city', 'persons.phones'])->find($orderId);
        if ($order) {
            $pushed = app(\App\Services\PartnerApiService::class)->pushCreatedToSuperpart($order);
            $this->line($pushed
                ? 'SuperPart: webhook partner-order-created отправлен (или источник не SP — пропуск).'
                : 'SuperPart: уведомление не ушло (не настроен / не SP-источник / ошибка HTTP).');
        }

        $this->info('Создана заявка №'.$orderId);
        $this->line('Город: '.$city->city_name.' (city_id='.$city->city_id.')');
        $this->line('Адрес: '.$street.', д. '.$house.($flat !== null ? ', кв. '.$flat : ''));
        $this->line('Клиент: '.$personName.', тел. '.$phoneDigits);

        return self::SUCCESS;
    }

    private function resolveCity(): ?City
    {
        $raw = $this->option('city');
        $cityId = ($raw === null || $raw === '') ? self::DEFAULT_CITY_ID : (int) $raw;

        return City::where('city_id', $cityId)->where('is_active', true)->first()
            ?? City::where('city_id', $cityId)->first();
    }

    private function creatorUserId(): int
    {
        $uid = $this->option('creator');

        return ($uid === null || $uid === '') ? self::DEFAULT_CREATOR_USER_ID : (int) $uid;
    }

    private function resolveCreator(): ?User
    {
        return User::where('user_id', $this->creatorUserId())->first();
    }

    private function resolveSource(int $cityId): ?Source
    {
        $sid = $this->option('source');
        if ($sid !== null && $sid !== '') {
            return Source::where('source_id', (int) $sid)->where('is_active', true)->first()
                ?? Source::where('source_id', (int) $sid)->first();
        }

        return Source::where('city_id', $cityId)->where('is_active', true)->orderBy('source_id')->first();
    }

    /** Уникальный 10-значный номер для person_phones (каждый запуск — новый). */
    private function uniquePhoneDigits(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $n = (string) random_int(1_000_000_000, 9_999_999_999);
            if (! PersonPhone::where('phone_number', $n)->exists()) {
                return $n;
            }
        }

        return substr(str_pad((string) microtime(true), 10, '0', STR_PAD_LEFT), 0, 10);
    }
}
