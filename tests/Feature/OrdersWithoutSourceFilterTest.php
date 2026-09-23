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

class OrdersWithoutSourceFilterTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'developer'],
            ['role_name' => 'Разработчик', 'is_active' => true],
        );

        $user = User::query()->create([
            'user_name' => 'Разработчик',
            'email' => 'dev-without-source@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user;
    }

    private function makeOrder(User $user, Address $address, ?int $sourceId): Order
    {
        $order = new Order([
            'datetime_order' => now(),
            'order_status' => 'pending',
            'order_type' => 'new',
            'address_id' => $address->address_id,
            'source_id' => $sourceId,
            'order_created_at' => now(),
        ]);
        $order->order_created_by = $user->user_id;
        $order->save();

        return $order->fresh();
    }

    public function test_without_source_lists_only_orders_missing_source(): void
    {
        $user = $this->developer();
        $city = City::query()->create(['city_name' => 'Тестград']);
        $person = Person::query()->create(['person_name' => 'Клиент Без Источника']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Тестовая',
            'house' => '1',
        ]);
        $source = Source::query()->create([
            'source_name' => 'Листовка',
            'source_format' => 'offline',
            'source_kind' => 'flyer',
            'is_active' => true,
        ]);

        $without = $this->makeOrder($user, $address, null);
        $withSource = $this->makeOrder($user, $address, (int) $source->source_id);

        $html = $this->actingAs($user)
            ->get(route('orders.index', ['without_source' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) $without->order_id, $html);
        $this->assertStringNotContainsString('order_id='.$withSource->order_id, $html);
        $this->assertStringNotContainsString('/orders/'.$withSource->order_id, $html);
    }

    public function test_sources_page_shows_without_source_chip(): void
    {
        $user = $this->developer();

        $this->actingAs($user)
            ->get(route('management.sources.index'))
            ->assertOk()
            ->assertSee('Без источника')
            ->assertSee(route('orders.index', ['without_source' => 1]), false);
    }
}
