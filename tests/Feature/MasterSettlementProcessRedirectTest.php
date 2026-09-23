<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\City;
use App\Models\Order;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterSettlementProcessRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'developer'],
            ['role_name' => 'Разработчик', 'is_active' => true],
        );
        $user = User::query()->create([
            'user_name' => 'Тест Касса',
            'email' => 'settlement-test@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user;
    }

    public function test_process_master_redirects_with_success_flash(): void
    {
        $actor = $this->actor();
        $master = User::query()->create([
            'user_name' => 'Мастер',
            'email' => 'master-settlement@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $city = City::query()->create(['city_name' => 'Тестград']);
        $person = Person::query()->create(['person_name' => 'Клиент']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Тестовая',
            'house' => '1',
        ]);

        $order = new Order([
            'datetime_order' => now(),
            'order_status' => 'completed',
            'order_type' => 'new',
            'order_core' => 'core',
            'address_id' => $address->address_id,
            'master_id' => $master->user_id,
            'amount_paid' => 1000,
            'amount_comp' => 0,
            'order_created_at' => now(),
            'order_closed_at' => now(),
        ]);
        $order->order_created_by = $actor->user_id;
        $order->save();

        $this->actingAs($actor)
            ->post(route('cfm.master-settlement.process', $master->user_id), [
                'order_ids' => [$order->order_id],
            ])
            ->assertRedirect(route('cfm.master-settlement.show', $master->user_id))
            ->assertSessionHas('success');

        $this->assertNotNull($order->fresh()->master_handed_over_at);
    }
}
