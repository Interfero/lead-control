<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\PersonPhone;
use App\Services\PersonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonPhoneSearchDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_search_returns_one_row_with_latest_visit_order(): void
    {
        $city = City::query()->create(['city_name' => 'Дзержинск '.uniqid(), 'is_active' => true]);
        $phone = '9001112233';

        $early = $this->personWithOrder($city, $phone, 'Александр', now()->subDays(4), 'pending');
        $latest = $this->personWithOrder($city, $phone, 'Александр', now()->addDay(), 'cancelled_cc');
        $this->personWithOrder($city, $phone, 'Александр', now()->subDays(2), 'pending');

        $results = app(PersonService::class)->search(null, $phone, null);

        $this->assertCount(1, $results);
        $this->assertSame((int) $latest['order']->order_id, $results[0]['order_id']);
        $this->assertSame((int) $latest['person']->person_id, $results[0]['person_id']);
        $this->assertNotSame((int) $early['person']->person_id, $results[0]['person_id']);
    }

    public function test_create_reuses_person_with_same_phone(): void
    {
        $city = City::query()->create(['city_name' => 'Город '.uniqid(), 'is_active' => true]);
        $svc = app(PersonService::class);

        $first = $svc->create([
            'person_name' => 'Иван',
            'person_age' => 30,
            'phone_number' => '9001112244',
            'city_id' => $city->city_id,
            'street' => 'Ленина',
            'house' => '1',
            'flat' => '2',
        ]);
        $second = $svc->create([
            'person_name' => 'Иван',
            'person_age' => 30,
            'phone_number' => '9001112244',
            'city_id' => $city->city_id,
            'street' => 'Ленина',
            'house' => '1',
            'flat' => '2',
        ]);

        $this->assertSame((int) $first->person_id, (int) $second->person_id);
        $this->assertSame(1, Person::query()->count());
        $this->assertSame(1, PersonPhone::query()->where('phone_number', '9001112244')->count());
        $this->assertSame(1, Address::query()->where('person_id', $first->person_id)->count());
    }

    /**
     * @return array{person: Person, order: Order}
     */
    private function personWithOrder(City $city, string $phone, string $name, $visitAt, string $status): array
    {
        $person = Person::query()->create([
            'person_name' => $name,
            'person_age' => 30,
        ]);
        PersonPhone::query()->create([
            'person_id' => $person->person_id,
            'phone_number' => $phone,
        ]);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Галочкина',
            'house' => '8',
            'flat' => 'Набрать по приезде',
        ]);
        $order = new Order([
            'datetime_order' => $visitAt,
            'order_status' => $status,
            'order_type' => 'new',
            'order_core' => 'core',
            'address_id' => $address->address_id,
            'amount_paid' => 0,
            'amount_comp' => 0,
            'order_created_at' => now(),
        ]);
        $order->save();
        $order->persons()->attach($person->person_id);

        return ['person' => $person->fresh(), 'order' => $order->fresh()];
    }
}
