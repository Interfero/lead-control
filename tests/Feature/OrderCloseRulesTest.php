<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Document;
use App\Models\Order;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCloseRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        CfmCategory::query()->create([
            'cfm_cat_name' => 'Поступление с Заказов',
            'cfm_cat_group' => 'inflows',
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => true,
        ]);
        [$this->actor, $this->order] = $this->makeOpenOrder();
    }

    public function test_cannot_complete_without_documents(): void
    {
        $this->order->amount_paid = 5000;
        $this->order->save();

        $check = app(OrderService::class)->canComplete($this->order->fresh(['documents']));

        $this->assertFalse($check['can']);
        $this->assertStringContainsString('документ', implode(' ', $check['errors']));
    }

    public function test_cannot_complete_with_expense_but_only_parts_photos(): void
    {
        $this->order->amount_paid = 5000;
        $this->order->amount_comp = 800;
        $this->order->save();
        $this->attachDoc($this->order, 'parts_photos');

        $check = app(OrderService::class)->canComplete($this->order->fresh(['documents']));

        $this->assertFalse($check['can']);
        $this->assertStringContainsString('чек расхода', implode(' ', $check['errors']));
    }

    public function test_complete_with_expense_requires_receipts_and_sets_completed(): void
    {
        $this->order->amount_paid = 5000;
        $this->order->amount_comp = 800;
        $this->order->save();
        $this->attachDoc($this->order, 'receipts');

        $svc = app(OrderService::class);
        $check = $svc->canComplete($this->order->fresh(['documents']));
        $this->assertTrue($check['can'], implode('; ', $check['errors']));

        $svc->complete($this->order->fresh(), (int) $this->actor->user_id);
        $fresh = $this->order->fresh();

        $this->assertSame('completed', $fresh->order_status);
        $this->assertNotNull($fresh->order_closed_at);
        $this->assertSame(1, CfmOperation::query()->where('related_order_id', $fresh->order_id)->count());
    }

    public function test_complete_paid_zero_becomes_rejected_without_cashbox(): void
    {
        $this->order->amount_paid = 0;
        $this->order->amount_comp = 0;
        $this->order->save();
        $this->attachDoc($this->order, 'contract');

        $svc = app(OrderService::class);
        $this->assertTrue($svc->canComplete($this->order->fresh(['documents']))['can']);
        $svc->complete($this->order->fresh(), (int) $this->actor->user_id);

        $fresh = $this->order->fresh();
        $this->assertSame('rejected', $fresh->order_status);
        $this->assertNotNull($fresh->order_closed_at);
        $this->assertSame(0, CfmOperation::query()->where('related_order_id', $fresh->order_id)->count());
    }

    public function test_artisan_fixes_completed_zero_to_rejected(): void
    {
        $this->order->amount_paid = 0;
        $this->order->order_status = 'completed';
        $this->order->order_closed_at = now();
        $this->order->order_closed_by = $this->actor->user_id;
        $this->order->save();

        $this->artisan('orders:fix-zero-paid-rejected')
            ->assertSuccessful();

        $this->assertSame('rejected', $this->order->fresh()->order_status);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function makeOpenOrder(): array
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'developer'],
            ['role_name' => 'Разработчик', 'is_active' => true],
        );
        $user = User::query()->create([
            'user_name' => 'Тест закрытия',
            'email' => 'close-rules-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        $masterRole = Role::query()->firstOrCreate(
            ['role_code' => 'master'],
            ['role_name' => 'Мастер', 'is_active' => true],
        );
        $master = User::query()->create([
            'user_name' => 'Мастер закрытия',
            'email' => 'master-close-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $master->roles()->attach($masterRole->role_id);

        $city = City::query()->create(['city_name' => 'Тест '.uniqid()]);
        $person = Person::query()->create(['person_name' => 'Клиент']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Тестовая',
            'house' => '1',
        ]);

        $order = new Order([
            'datetime_order' => now(),
            'order_status' => 'in_progress',
            'order_type' => 'new',
            'order_core' => 'core',
            'address_id' => $address->address_id,
            'master_id' => $master->user_id,
            'amount_paid' => 0,
            'amount_comp' => 0,
            'order_created_at' => now(),
        ]);
        $order->order_created_by = $user->user_id;
        $order->save();
        $order->persons()->attach($person->person_id);

        return [$user, $order->fresh()];
    }

    private function attachDoc(Order $order, string $category): void
    {
        $doc = new Document([
            'documentable_type' => Order::class,
            'documentable_id' => $order->order_id,
            'document_category' => $category,
            'file_name' => $category.'.jpg',
            'file_path' => 'documents/test/'.$category.'.jpg',
            'file_mime' => 'image/jpeg',
            'file_size' => 120,
        ]);
        $doc->uploaded_by = $this->actor->user_id;
        $doc->save();
    }
}
