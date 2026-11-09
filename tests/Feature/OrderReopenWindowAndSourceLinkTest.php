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

class OrderReopenWindowAndSourceLinkTest extends TestCase
{
    use RefreshDatabase;

    private const AVITO = 'https://www.avito.ru/brands/i123456';

    public function test_branch_head_can_reopen_within_12_hours(): void
    {
        [$head, $order] = $this->closedOrderWithBranchHead(now()->subHours(11));

        $this->actingAs($head)
            ->post(route('orders.reopen', $order->order_id))
            ->assertSessionHas('success');

        $this->assertNull($order->fresh()->order_closed_at);
        $this->assertSame('in_progress', $order->fresh()->order_status);
    }

    public function test_branch_head_cannot_reopen_after_12_hours(): void
    {
        [$head, $order] = $this->closedOrderWithBranchHead(now()->subHours(13));

        $this->actingAs($head)
            ->post(route('orders.reopen', $order->order_id))
            ->assertForbidden();

        $this->assertNotNull($order->fresh()->order_closed_at);
    }

    public function test_branch_head_cannot_reopen_order_of_foreign_city(): void
    {
        [$head, $order] = $this->closedOrderWithBranchHead(now()->subHour());
        $head->cities()->detach();

        $this->actingAs($head)
            ->post(route('orders.reopen', $order->order_id))
            ->assertForbidden();

        $this->assertNotNull($order->fresh()->order_closed_at);
    }

    public function test_regional_director_still_reopens_without_time_limit(): void
    {
        [, $order] = $this->closedOrderWithBranchHead(now()->subDays(5));
        $rd = $this->userWithRole('regional_director');
        $rd->cities()->attach($order->address->city_id);

        $this->actingAs($rd)
            ->post(route('orders.reopen', $order->order_id))
            ->assertSessionHas('success');

        $this->assertNull($order->fresh()->order_closed_at);
    }

    public function test_store_appends_superpart_source_link_to_order_adds(): void
    {
        [$user, $person, $address] = $this->dispatcherWithAddress();
        $source = $this->makeSource(available: true);

        $this->actingAs($user)->post(route('orders.store'), $this->storePayload($person, $address, $source, 'Не работает стиралка'))
            ->assertRedirect();

        $this->assertSame("Не работает стиралка\n".self::AVITO, Order::query()->latest('order_id')->first()->order_adds);
    }

    public function test_store_uses_link_alone_when_description_empty_and_does_not_duplicate(): void
    {
        [$user, $person, $address] = $this->dispatcherWithAddress();
        $source = $this->makeSource(available: true);

        $this->actingAs($user)->post(route('orders.store'), $this->storePayload($person, $address, $source, null))
            ->assertRedirect();
        $this->assertSame(self::AVITO, Order::query()->latest('order_id')->first()->order_adds);

        $this->actingAs($user)->post(route('orders.store'), $this->storePayload($person, $address, $source, 'уже есть '.self::AVITO))
            ->assertRedirect();
        $this->assertSame('уже есть '.self::AVITO, Order::query()->latest('order_id')->first()->order_adds);
    }

    public function test_store_does_not_add_link_for_source_outside_superpart(): void
    {
        [$user, $person, $address] = $this->dispatcherWithAddress();
        $source = $this->makeSource(available: false);

        $this->actingAs($user)->post(route('orders.store'), $this->storePayload($person, $address, $source, 'Текст'))
            ->assertRedirect();

        $this->assertSame('Текст', Order::query()->latest('order_id')->first()->order_adds);
    }

    public function test_order_page_shows_rk_link_to_dispatcher(): void
    {
        [$user, $person, $address] = $this->dispatcherWithAddress();
        $source = $this->makeSource(available: true);

        $this->actingAs($user)->post(route('orders.store'), $this->storePayload($person, $address, $source, 'Текст'))
            ->assertRedirect();
        $order = Order::query()->latest('order_id')->first();

        $this->actingAs($user)->get(route('orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Ссылка РК:')
            ->assertSee(self::AVITO);
    }

    // ---- helpers ----

    private function storePayload(Person $person, Address $address, Source $source, ?string $adds): array
    {
        return [
            'person_id' => $person->person_id,
            'address_id' => $address->address_id,
            'datetime_order' => now()->addDay()->format('Y-m-d H:i:s'),
            'order_type' => 'new',
            'order_status' => 'not_processed',
            'order_adds' => $adds,
            'source_id' => $source->source_id,
        ];
    }

    private function makeSource(bool $available): Source
    {
        return Source::query()->create([
            'source_name' => 'Партнёр '.uniqid(),
            'source_kind' => Source::KIND_PARTY,
            'source_url' => self::AVITO,
            'use_source_url' => true,
            'is_active' => true,
            'available_for_superpart' => $available,
        ]);
    }

    private function dispatcherWithAddress(): array
    {
        $user = $this->userWithRole('call_center');
        $city = City::query()->create(['city_name' => 'Тест '.uniqid()]);
        $person = Person::query()->create(['person_name' => 'Клиент']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Тестовая',
            'house' => '1',
        ]);

        return [$user, $person, $address];
    }

    /** @return array{0: User, 1: Order} */
    private function closedOrderWithBranchHead($closedAt): array
    {
        [$creator, $person, $address] = $this->dispatcherWithAddress();
        $head = $this->userWithRole('branch_head');
        $head->cities()->attach($address->city_id);

        $order = new Order([
            'datetime_order' => now(),
            'order_status' => 'completed',
            'order_type' => 'new',
            'order_core' => 'core',
            'address_id' => $address->address_id,
            'amount_paid' => 0,
            'amount_comp' => 0,
            'order_created_at' => now()->subDay(),
        ]);
        $order->order_created_by = $creator->user_id;
        $order->order_closed_at = $closedAt;
        $order->order_closed_by = $creator->user_id;
        $order->save();
        $order->persons()->attach($person->person_id);

        return [$head, $order->fresh(['address'])];
    }

    private function userWithRole(string $code): User
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => $code],
            ['role_name' => $code, 'is_active' => true],
        );
        $user = User::query()->create([
            'user_name' => 'Тест '.$code,
            'email' => $code.'-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user;
    }
}
