<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Jobs\SyncOrderToGmJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GmOrderReviewScheduleClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gm_api.bearer_token' => 'test-gm-token']);
        Bus::fake();
        CfmCategory::query()->create([
            'cfm_cat_name' => 'Поступление с Заказов',
            'cfm_cat_group' => 'inflows',
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => true,
        ]);
    }

    public function test_review_from_in_progress_sd_closes_completed_not_review(): void
    {
        [$master, $order] = $this->masterWithOrder('in_progress_sd');

        $response = $this->gmJson('POST', '/api/v1/gm/orders/'.$order->order_id.'/review', $master, [
            'amountPaidRub' => 5000,
            'amountCompRub' => 0,
            'masterComment' => 'отписка',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.financialSnapshot.amountPaidRub', 5000)
            ->assertJsonPath('order.financialSnapshot.amountCompRub', 0)
            ->assertJsonPath('order.financialSnapshot.netAmountRub', 5000)
            ->assertJsonPath('order.financialSnapshot.orderCore', 'core')
            ->assertJsonPath('order.masterCloseComment', 'отписка');

        $this->assertSame('completed', $order->fresh()->order_status);
        $this->assertNotNull($order->fresh()->order_closed_at);
        $this->assertGreaterThan(0, (int) $response->json('order.financialSnapshot.masterSalaryRub'));
        $this->assertStringContainsString(
            Order::BRANCH_COMMENT_CLOSE_HEADING,
            (string) $order->fresh()->city_adds,
        );
        $this->assertSame(1, CfmOperation::query()->where('related_order_id', $order->order_id)->count());
        Bus::assertDispatched(SyncOrderToGmJob::class);
    }

    public function test_review_from_in_progress_is_allowed(): void
    {
        [$master, $order] = $this->masterWithOrder('in_progress');

        $this->gmJson('POST', '/api/v1/gm/orders/'.$order->order_id.'/review', $master, [
            'amountPaidRub' => 1000,
            'amountCompRub' => 0,
            'masterComment' => 'готово',
        ])->assertOk()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.financialSnapshot.orderCore', 'core');
    }

    public function test_review_from_existing_review_closes_completed_not_409(): void
    {
        [$master, $order] = $this->masterWithOrder('review');

        $this->gmJson('POST', '/api/v1/gm/orders/'.$order->order_id.'/review', $master, [
            'amountPaidRub' => 5000,
            'amountCompRub' => 0,
            'masterComment' => 'дожать проверку',
        ])->assertOk()->assertJsonPath('order.status', 'completed');

        $this->assertSame('completed', $order->fresh()->order_status);
    }

    public function test_review_repeat_on_closed_returns_409(): void
    {
        [$master, $order] = $this->masterWithOrder('in_progress_sd');

        $this->gmJson('POST', '/api/v1/gm/orders/'.$order->order_id.'/review', $master, [
            'amountPaidRub' => 5000,
            'amountCompRub' => 0,
            'masterComment' => 'отписка',
        ])->assertOk()->assertJsonPath('order.status', 'completed');

        $this->gmJson('POST', '/api/v1/gm/orders/'.$order->order_id.'/review', $master, [
            'amountPaidRub' => 5000,
            'amountCompRub' => 0,
            'masterComment' => 'ещё раз',
        ])->assertStatus(409)->assertJsonPath('code', 'order_already_final');
    }

    public function test_review_from_on_way_returns_422(): void
    {
        [$master, $order] = $this->masterWithOrder('on_way');

        $this->gmJson('POST', '/api/v1/gm/orders/'.$order->order_id.'/review', $master, [
            'amountPaidRub' => 5000,
            'amountCompRub' => 0,
            'masterComment' => 'отписка',
        ])->assertStatus(422)->assertJsonPath('code', 'invalid_status_transition');
    }

    public function test_list_and_detail_include_scheduled_at_and_claim_flag(): void
    {
        $visitAt = now()->setTime(16, 44, 0);
        [$master, $order] = $this->masterWithOrder('pending', $visitAt);

        $list = $this->gmJson('GET', '/api/v1/gm/orders', $master);
        $list->assertOk()->assertJsonPath('items.0.scheduledAt', $visitAt->toIso8601String());
        $list->assertJsonPath('items.0.hasClaim', false);
        $list->assertJsonPath('items.0.claim', null);
        $this->assertNotEquals($list->json('items.0.createdAt'), $list->json('items.0.scheduledAt'));

        $this->gmJson('GET', '/api/v1/gm/orders/'.$order->order_id, $master)
            ->assertOk()
            ->assertJsonPath('scheduledAt', $visitAt->toIso8601String())
            ->assertJsonPath('hasClaim', false);

        Complaint::query()->create([
            'order_id' => $order->order_id,
            'city_id' => $order->address->city_id,
            'complaint_type' => Complaint::TYPE_REPAIR_QUALITY,
            'complaint_text' => 'Клиент недоволен ремонтом',
            'complaint_status' => Complaint::STATUS_NEW,
            'complaint_created_at' => now(),
        ]);

        $this->gmJson('GET', '/api/v1/gm/orders/'.$order->order_id, $master)
            ->assertOk()
            ->assertJsonPath('hasClaim', true)
            ->assertJsonPath('claim.status', 'new')
            ->assertJsonPath('claim.summary', 'Клиент недоволен ремонтом');
    }

    public function test_master_does_not_see_another_masters_order(): void
    {
        [$owner] = $this->masterWithOrder('in_progress_sd');
        [, $foreignOrder] = $this->masterWithOrder('in_progress_sd');

        $this->gmJson('GET', '/api/v1/gm/orders/'.$foreignOrder->order_id, $owner)
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function masterWithOrder(string $status, $datetimeOrder = null): array
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'master'],
            ['role_name' => 'Мастер', 'is_active' => true],
        );

        $master = User::query()->create([
            'user_name' => 'Мастер GM '.uniqid(),
            'email' => uniqid('master-', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $master->roles()->attach($role->role_id);

        $city = City::query()->create(['city_name' => 'Тбилиси '.uniqid()]);
        $person = Person::query()->create(['person_name' => 'Клиент']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Тестовая',
            'house' => '1',
        ]);

        $order = new Order([
            'datetime_order' => $datetimeOrder ?? now()->addDay(),
            'order_status' => $status,
            'order_type' => 'new',
            'order_core' => 'core',
            'address_id' => $address->address_id,
            'master_id' => $master->user_id,
            'amount_paid' => 0,
            'amount_comp' => 0,
            'order_created_at' => now()->subHours(3),
        ]);
        $order->order_created_by = $master->user_id;
        $order->save();
        $order->persons()->attach($person->person_id);

        return [$master, $order->fresh(['address.city', 'persons'])];
    }

    private function gmJson(string $method, string $uri, User $master, array $body = [])
    {
        return $this->json($method, $uri, $body, [
            'Authorization' => 'Bearer test-gm-token',
            'X-GM-User-Id' => (string) $master->user_id,
        ]);
    }
}
