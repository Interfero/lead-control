<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Address;
use App\Models\City;
use App\Models\CityOpenTime;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonPhone;
use App\Models\Source;
use App\Models\User;

$city = City::query()->where('city_name', 'Краснодар')->first();
if (! $city) {
    fwrite(STDERR, "City Krasnodar not found\n");
    exit(1);
}

$address = Address::query()
    ->where('city_id', $city->city_id)
    ->with('person')
    ->first();

$person = $address?->person;

if (! $person) {
    $person = Person::create([
        'person_name' => 'Тест закреп Краснодар',
    ]);
    PersonPhone::create([
        'person_id' => $person->person_id,
        'phone_number' => '79000000099',
        'phone_adds' => 'тест',
    ]);
    $address = Address::create([
        'person_id' => $person->person_id,
        'city_id' => $city->city_id,
        'street' => 'Красная',
        'house' => '1',
        'flat' => null,
    ]);
}

$source = Source::query()
    ->where('city_id', $city->city_id)
    ->where('is_active', true)
    ->orderBy('source_id')
    ->first();

if (! $source) {
    $source = Source::query()
        ->where('is_active', true)
        ->orderBy('source_id')
        ->first();
}

$user = User::query()
    ->whereHas('roles', fn ($q) => $q->where('roles.role_code', 'call_center'))
    ->orWhereHas('roles', fn ($q) => $q->where('roles.role_code', 'developer'))
    ->orderBy('user_id')
    ->first();

if (! $user) {
    fwrite(STDERR, "No call_center/developer user found\n");
    exit(1);
}

$datetime = now()->addDay()->setTime(14, 0, 0)->format('Y-m-d H:i:s');
$tomorrow = now()->addDay()->toDateString();

$pin = CityOpenTime::query()
    ->where('city_id', $city->city_id)
    ->whereDate('begin_date', '<=', $tomorrow)
    ->whereDate('end_date', '>=', $tomorrow)
    ->first();

if (! $pin) {
    $pin = CityOpenTime::create([
        'city_id' => $city->city_id,
        'begin_date' => $tomorrow,
        'end_date' => $tomorrow,
        'time_from' => 14,
        'comment' => 'Тест закрепления — следующая заявка с 14:00',
        'created_by' => $user->user_id,
        'updated_by' => $user->user_id,
    ]);
}

$order = new Order([
    'address_id' => $address->address_id,
    'datetime_order' => $datetime,
    'order_type' => 'new',
    'order_core' => 'core',
    'equipment_type' => 'washing_machine',
    'order_status' => 'pending',
    'order_adds' => 'Тестовый заказ для проверки закрепления времени (Краснодар, 14:00 завтра)',
    'source_id' => $source?->source_id,
    'is_long_trip' => false,
    'order_created_at' => now(),
    'master_id' => null,
    'amount_paid' => 0,
    'amount_comp' => 0,
]);
$order->order_created_by = $user->user_id;
$order->save();

$order->persons()->attach($person->person_id);

echo json_encode([
    'order_id' => $order->order_id,
    'city' => $city->city_name,
    'datetime_order' => $datetime,
    'person_id' => $person->person_id,
    'address_id' => $address->address_id,
    'source_id' => $source?->source_id,
    'city_open_time_id' => $pin->city_open_time_id,
    'pin_summary' => $pin->summaryText(),
    'url' => 'https://lead-control.space.app/orders/' . $order->order_id,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
