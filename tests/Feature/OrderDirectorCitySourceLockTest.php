<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDirectorCitySourceLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider lockedCitySourceRoles
     */
    public function test_locked_roles_cannot_change_city_source_or_set_review(string $roleCode): void
    {
        [$head, $order, $otherCity, $otherSource] = $this->userWithOrder($roleCode);

        $this->actingAs($head)
            ->put(route('orders.update', $order->order_id), [
                'datetime_order' => $order->datetime_order->format('Y-m-d H:i:s'),
                'shift_adds' => '',
                'amount_paid' => 0,
                'amount_comp' => 0,
                'city_adds' => '',
                'master_id' => $order->master_id,
                'order_status' => 'review',
                'address_city_id' => $otherCity->city_id,
                'source_id' => $otherSource->source_id,
            ])
            ->assertSessionHasErrors('order_status');

        $fresh = $order->fresh(['address']);
        $this->assertSame('pending', $fresh->order_status);
        $this->assertSame((int) $order->address->city_id, (int) $fresh->address->city_id);
        $this->assertSame((int) $order->source_id, (int) $fresh->source_id);
    }

    /**
     * @dataProvider lockedCitySourceRoles
     */
    public function test_locked_roles_card_hides_city_and_source_lists(string $roleCode): void
    {
        [$head, $order] = array_slice($this->userWithOrder($roleCode), 0, 2);

        $this->actingAs($head)
            ->get(route('orders.show', $order->order_id))
            ->assertOk()
            ->assertDontSee('name="address_city_id"', false)
            ->assertDontSee('name="source_id"', false)
            ->assertDontSee('value="review"', false);
    }

    /** @return list<array{0: string}> */
    public static function lockedCitySourceRoles(): array
    {
        return [
            ['branch_head'],
            ['regional_director'],
            ['senior_manager'],
        ];
    }

    /**
     * @return array{0: User, 1: Order, 2: City, 3: Source}
     */
    private function userWithOrder(string $roleCode): array
    {
        $headRole = Role::query()->firstOrCreate(
            ['role_code' => $roleCode],
            ['role_name' => $roleCode, 'is_active' => true],
        );
        $masterRole = Role::query()->firstOrCreate(
            ['role_code' => 'master'],
            ['role_name' => 'Мастер', 'is_active' => true],
        );

        $head = User::query()->create([
            'user_name' => 'Директор тест',
            'email' => 'bh-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $head->roles()->attach($headRole->role_id);

        $master = User::query()->create([
            'user_name' => 'Мастер тест',
            'email' => 'm-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $master->roles()->attach($masterRole->role_id);

        $city = City::query()->create(['city_name' => 'Город А '.uniqid()]);
        $otherCity = City::query()->create(['city_name' => 'Город Б '.uniqid()]);
        $head->cities()->attach($city->city_id);

        $source = Source::query()->create(['source_name' => 'РК А', 'is_active' => true]);
        $otherSource = Source::query()->create(['source_name' => 'РК Б', 'is_active' => true]);

        $person = Person::query()->create(['person_name' => 'Клиент']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Тестовая',
            'house' => '1',
        ]);

        $order = new Order([
            'datetime_order' => now(),
            'order_status' => 'pending',
            'order_type' => 'new',
            'order_core' => 'core',
            'address_id' => $address->address_id,
            'master_id' => $master->user_id,
            'source_id' => $source->source_id,
            'amount_paid' => 0,
            'amount_comp' => 0,
            'order_created_at' => now(),
        ]);
        $order->order_created_by = $head->user_id;
        $order->save();
        $order->persons()->attach($person->person_id);

        return [$head->fresh(['roles', 'cities']), $order->fresh(['address']), $otherCity, $otherSource];
    }
}
