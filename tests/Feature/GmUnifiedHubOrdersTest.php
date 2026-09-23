<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\City;
use App\Models\HubMasterLink;
use App\Models\Order;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ТЗ «расширить GM API данными Единого хаба и KP-Lead»:
 * GM ходит только в /api/v1/gm/*, а Lead Control отдаёт объединённую выборку LC + KP-Lead.
 */
class GmUnifiedHubOrdersTest extends TestCase
{
    use RefreshDatabase;

    private const KP_CRM_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gm_api.bearer_token' => 'test-gm-token',
            'services.unified_hub.enabled' => true,
            'services.unified_hub.connection' => 'hub',
            'services.unified_hub.kp_crm_id' => self::KP_CRM_ID,
        ]);

        $this->createHubSchema();
    }

    public function test_orders_merge_lc_and_kp_with_source_and_unique_ids(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $lcOrder = $this->lcOrder($master, $city, 'in_progress');

        // Конфликт ID: LC-заказ и KP-заявка с одинаковым числом
        $this->kpOrder((string) $lcOrder->order_id, $city->city_id, 'completed', 'closed', 12000);
        $this->kpOrder(
            '2645253',
            $city->city_id,
            'pending',
            'new',
            null,
            null,
            'Псков (рабочий посёлок Палкино)',
            'Рабочая улица, 3',
        );
        $this->link($master, $city, 'Цыганков Кирилл');

        $response = $this->gmJson('GET', '/api/v1/gm/orders', $master);
        $response->assertOk();

        $items = $response->json('items');
        $this->assertCount(3, $items);

        $ids = array_column($items, 'id');
        $this->assertSame(count($ids), count(array_unique($ids, SORT_REGULAR)), 'ID заявок должны быть уникальны');
        $this->assertContains($lcOrder->order_id, $ids);
        $this->assertContains('kp-'.$lcOrder->order_id, $ids);
        $this->assertContains('kp-2645253', $ids);

        $sources = array_column($items, 'source');
        $this->assertContains('lc', $sources);
        $this->assertContains('kp_lead', $sources);

        $kp = collect($items)->firstWhere('id', 'kp-2645253');
        $this->assertSame('kp_lead', $kp['source']);
        $this->assertSame('2645253', $kp['sourceOrderId']);
        $this->assertSame((string) $master->user_id, $kp['masterId']);
        $this->assertSame($city->city_id, $kp['cityId']);
        $this->assertSame('Псков (рабочий посёлок Палкино)', $kp['cityName']);
        $this->assertSame('Рабочая улица, 3', $kp['address']);
        $this->assertSame('Псков (рабочий посёлок Палкино), Рабочая улица, 3', $kp['addressFull']);
        $this->assertSame('pending', $kp['status']);
        $this->assertArrayHasKey('financialSnapshot', $kp);

        $response->assertJsonPath('partial', false);
        $this->assertSame(3, $response->json('total'));
    }

    public function test_lc_order_payload_keeps_numeric_id_for_backward_compatibility(): void
    {
        [$master, $city] = $this->master('Мастер LC');
        $order = $this->lcOrder($master, $city, 'pending');

        $response = $this->gmJson('GET', '/api/v1/gm/orders', $master);

        $response->assertOk()
            ->assertJsonPath('items.0.id', $order->order_id)
            ->assertJsonPath('items.0.source', 'lc')
            ->assertJsonPath('items.0.orderNumber', (string) $order->order_id);

        $this->gmJson('GET', '/api/v1/gm/orders/'.$order->order_id, $master)
            ->assertOk()
            ->assertJsonPath('id', $order->order_id);
    }

    public function test_kp_order_detail_is_available_by_canonical_id(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->kpOrder('2645253', $city->city_id, 'pending', 'new', null);
        $this->link($master, $city, 'Цыганков Кирилл');

        $this->gmJson('GET', '/api/v1/gm/orders/kp-2645253', $master)
            ->assertOk()
            ->assertJsonPath('id', 'kp-2645253')
            ->assertJsonPath('source', 'kp_lead')
            ->assertJsonPath('sourceOrderId', '2645253');
    }

    public function test_kp_office_hidden_before_window_even_if_cached(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->link($master, $city, 'Цыганков Кирилл');
        $this->kpOrder(
            '2651619',
            $city->city_id,
            'pending',
            'new',
            null,
            now()->addDays(2),
            'Псков',
            'пр-кт Энтузиастов, 3',
            'кв / офис: 12',
        );

        $kp = $this->gmJson('GET', '/api/v1/gm/orders', $master)->assertOk()->json('items.0');
        $this->assertSame('пр-кт Энтузиастов, 3', $kp['address']);
        $this->assertSame('Псков, пр-кт Энтузиастов, 3', $kp['addressFull']);
        $this->assertStringNotContainsString('кв', (string) $kp['addressFull']);
    }

    public function test_kp_office_appended_in_progress_from_cache(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->link($master, $city, 'Цыганков Кирилл');
        $this->kpOrder(
            '2651619',
            $city->city_id,
            'in_progress',
            'in_progress',
            null,
            now()->addDays(2),
            'Псков',
            'пр-кт Энтузиастов, 3',
            'кв / офис: 12',
        );

        $kp = $this->gmJson('GET', '/api/v1/gm/orders/kp-2651619', $master)->assertOk()->json();
        $this->assertSame('пр-кт Энтузиастов, 3, кв / офис: 12', $kp['address']);
        $this->assertSame('Псков, пр-кт Энтузиастов, 3, кв / офис: 12', $kp['addressFull']);
    }

    public function test_kp_office_auto_reveal_via_desk_when_cache_empty(): void
    {
        config([
            'services.unified_hub.write_driver' => 'http',
            'services.unified_hub.write_url' => 'https://desk.test',
            'services.unified_hub.write_token' => 'desk-token',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://desk.test/desk/internal/orders/2651619/reveal-office' => \Illuminate\Support\Facades\Http::response([
                'ok' => true,
                'text' => 'кв / офис: 7',
                'from_cache' => false,
            ], 200),
        ]);

        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->link($master, $city, 'Цыганков Кирилл');
        $this->kpOrder(
            '2651619',
            $city->city_id,
            'on_way',
            'on_way',
            null,
            now()->addHour(),
            'Псков',
            'пр-кт Энтузиастов, 3',
            null,
        );

        $kp = $this->gmJson('GET', '/api/v1/gm/orders/kp-2651619', $master)->assertOk()->json();
        $this->assertStringContainsString('кв / офис: 7', (string) $kp['addressFull']);

        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_master_cannot_read_another_masters_kp_order(): void
    {
        [$owner, $city] = $this->master('Цыганков Кирилл Сергеевич');
        [$other] = $this->master('Чужой Мастер', $city);

        $this->kpOrder('2645253', $city->city_id, 'pending', 'new', null);
        $this->link($owner, $city, 'Цыганков Кирилл');

        $this->gmJson('GET', '/api/v1/gm/orders/kp-2645253', $other)->assertNotFound();

        $this->gmJson('GET', '/api/v1/gm/orders', $other)
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_metrics_count_kp_orders_without_double_counting(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->link($master, $city, 'Цыганков Кирилл');

        $this->kpOrder('1', $city->city_id, 'completed', 'closed', 10000, now());
        $this->kpOrder('2', $city->city_id, 'completed', 'closed', 20000, now());
        $this->kpOrder('3', $city->city_id, 'rejected', 'closed', 0, now());
        $this->kpOrder('4', $city->city_id, 'completed', 'closed', 999999, now()->subMonths(2));

        $response = $this->gmJson('GET', '/api/v1/gm/users/me/metrics', $master);

        $response->assertOk()
            ->assertJsonPath('primaryClosedOrdersMonth', 2)
            ->assertJsonPath('primaryAvgCheckRub', 15000)
            ->assertJsonPath('masterMonthRefusals', 1)
            ->assertJsonPath('bySource.kp_lead.closedOrdersMonth', 2)
            ->assertJsonPath('bySource.lc.closedOrdersMonth', 0)
            ->assertJsonPath('partial', false);

        // 10000 → 40%, 20000 → 50%
        $this->assertSame(4000 + 10000, (int) $response->json('masterEarnedRub'));
    }

    public function test_metrics_report_partial_data_when_source_is_unavailable(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->link($master, $city, 'Цыганков Кирилл');
        $this->kpOrder('1', $city->city_id, 'completed', 'closed', 10000, now());

        Schema::connection('hub')->drop('orders_cache');

        $response = $this->gmJson('GET', '/api/v1/gm/users/me/metrics', $master);

        $response->assertOk()
            ->assertJsonPath('partial', true)
            ->assertJsonPath('sources.1.status', 'unavailable')
            ->assertJsonPath('sources.1.available', false);
    }

    public function test_kp_order_accept_updates_status(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->kpOrder('2646800', $city->city_id, 'pending', 'new', null);
        $this->link($master, $city, 'Цыганков Кирилл');

        $this->gmJson('POST', '/api/v1/gm/orders/kp-2646800/accept', $master)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('order.id', 'kp-2646800')
            ->assertJsonPath('order.status', 'on_way');

        $this->gmJson('GET', '/api/v1/gm/orders/kp-2646800', $master)
            ->assertOk()
            ->assertJsonPath('status', 'on_way');
    }

    public function test_kp_order_accept_rejects_final_with_clear_code(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->kpOrder('2646801', $city->city_id, 'completed', 'closed', 1000, now());
        $this->link($master, $city, 'Цыганков Кирилл');

        $this->gmJson('POST', '/api/v1/gm/orders/kp-2646801/accept', $master)
            ->assertStatus(409)
            ->assertJsonPath('code', 'order_already_final')
            ->assertJsonPath('message', 'Заявка уже закрыта или в финальном статусе.');
    }

    public function test_kp_order_review_still_returns_not_supported(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->kpOrder('2645253', $city->city_id, 'pending', 'new', null);
        $this->link($master, $city, 'Цыганков Кирилл');

        $this->gmJson('POST', '/api/v1/gm/orders/kp-2645253/review', $master, [
            'amountPaidRub' => 100,
            'amountCompRub' => 0,
            'masterComment' => 'тест',
        ])->assertStatus(409)->assertJsonPath('code', 'source_action_not_supported');
    }

    public function test_sources_endpoint_reports_sync_state(): void
    {
        [$master, $city] = $this->master('Цыганков Кирилл Сергеевич');
        $this->link($master, $city, 'Цыганков Кирилл');
        $this->kpOrder('1', $city->city_id, 'completed', 'closed', 1000, now());

        $this->gmJson('GET', '/api/v1/gm/orders/sources', $master)
            ->assertOk()
            ->assertJsonPath('sources.1.source', 'kp_lead')
            ->assertJsonPath('partial', false)
            ->assertJsonPath('cities.0.cityId', $city->city_id);
    }

    // --- helpers -------------------------------------------------------

    /**
     * Тест пересоздаёт схему хаба, поэтому он обязан работать только с тестовой базой.
     * Кэш конфига (config:cache) игнорирует env из phpunit.xml — без этой проверки
     * соединение 'hub' может указать на боевой lead_desk и снести orders_cache.
     */
    private function assertSafeHubConnection(): void
    {
        $database = (string) DB::connection('hub')->getDatabaseName();
        $isMemorySqlite = DB::connection('hub')->getDriverName() === 'sqlite'
            && in_array($database, [':memory:', ''], true);

        if (! $isMemorySqlite && ! str_contains(strtolower($database), 'test')) {
            $this->fail(
                'Небезопасная база для соединения hub: "'.$database.'". '
                .'Тест пересоздаёт orders_cache, имя базы обязано содержать "test". '
                .'Проверьте, что bootstrap/cache/config.php не перекрывает env тестов.'
            );
        }
    }

    private function createHubSchema(): void
    {
        $this->assertSafeHubConnection();

        $schema = Schema::connection('hub');

        $schema->dropIfExists('orders_cache');
        $schema->dropIfExists('crm_connections');

        $schema->create('crm_connections', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('type', 64);
            $table->string('status', 16)->default('active');
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
        });

        $schema->create('orders_cache', function ($table) {
            $table->id();
            $table->unsignedBigInteger('crm_id');
            $table->string('external_id', 64);
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->string('status', 32);
            $table->string('raw_status', 64)->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_age', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('address')->nullable();
            $table->string('address_office', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('master_name')->nullable();
            $table->string('master_external_id', 64)->nullable();
            $table->integer('total_amount')->nullable();
            $table->integer('paid_amount')->nullable();
            $table->integer('parts_amount')->nullable();
            $table->timestamp('created_at_local')->nullable();
            $table->timestamp('call_at_local')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamp('updated_at_local')->nullable();
            $table->string('order_type', 32)->nullable();
            $table->string('order_core', 16)->nullable();
            $table->boolean('is_noncore')->default(false);
            $table->boolean('is_long_trip')->default(false);
            $table->boolean('is_satellite')->default(false);
            $table->boolean('is_partner_order')->default(false);
            $table->text('comments')->nullable();
            $table->text('documents')->nullable();
            $table->timestamp('last_synced_at')->nullable();
        });

        DB::connection('hub')->table('crm_connections')->insert([
            ['id' => 1, 'name' => 'Lead Control', 'type' => 'crm1_api', 'status' => 'active', 'last_sync_at' => now()],
            ['id' => self::KP_CRM_ID, 'name' => 'kp-lead-centre', 'type' => 'crm2_http', 'status' => 'active', 'last_sync_at' => now()],
        ]);
    }

    /**
     * @return array{0: User, 1: City}
     */
    private function master(string $name, ?City $city = null): array
    {
        $role = Role::query()->firstOrCreate(
            ['role_code' => 'master'],
            ['role_name' => 'Мастер', 'is_active' => true],
        );

        $city ??= City::query()->create([
            'city_name' => 'Псков '.uniqid(),
            'city_timezone' => 'Europe/Moscow',
            'is_active' => true,
        ]);

        $master = User::query()->create([
            'user_name' => $name,
            'email' => uniqid('m-', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $master->roles()->attach($role->role_id);
        $master->cities()->attach($city->city_id);

        return [$master->fresh(['roles', 'cities']), $city];
    }

    private function link(User $master, City $city, string $kpName): HubMasterLink
    {
        return HubMasterLink::query()->create([
            'source' => HubMasterLink::SOURCE_KP,
            'external_master_id' => null,
            'external_master_name' => $kpName,
            'name_key' => HubMasterLink::normalizeName($kpName),
            'city_id' => $city->city_id,
            'user_id' => $master->user_id,
            'link_type' => HubMasterLink::TYPE_AUTO,
            'is_active' => true,
        ]);
    }

    private function kpOrder(
        string $externalId,
        int $cityId,
        string $rawStatus,
        string $status,
        ?int $totalAmount,
        $createdAt = null,
        string $cityName = 'Псков',
        string $address = 'ул. Ленина, д. 1',
        ?string $addressOffice = null,
    ): void {
        DB::connection('hub')->table('orders_cache')->insert([
            'crm_id' => self::KP_CRM_ID,
            'external_id' => $externalId,
            'city_id' => $cityId,
            'city_name' => $cityName,
            'status' => $status,
            'raw_status' => $rawStatus,
            'client_name' => 'Клиент КП',
            'phone' => '9001234567',
            'address' => $address,
            'address_office' => $addressOffice,
            'description' => 'Ремонт',
            'master_name' => 'Цыганков Кирилл',
            'master_external_id' => null,
            'total_amount' => $totalAmount,
            'created_at_local' => ($createdAt ?? now())->format('Y-m-d H:i:s'),
            'call_at_local' => ($createdAt ?? now())->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Moscow',
            'order_type' => 'first',
            'last_synced_at' => now(),
        ]);
    }

    private function lcOrder(User $master, City $city, string $status): Order
    {
        $person = Person::query()->create(['person_name' => 'Клиент LC']);
        $address = Address::query()->create([
            'person_id' => $person->person_id,
            'city_id' => $city->city_id,
            'street' => 'Мира',
            'house' => '2',
        ]);

        $order = new Order([
            'datetime_order' => now()->addDay(),
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

        return $order->fresh(['address.city', 'persons']);
    }

    private function gmJson(string $method, string $uri, User $master, array $body = [])
    {
        return $this->json($method, $uri, $body, [
            'Authorization' => 'Bearer test-gm-token',
            'X-GM-User-Id' => (string) $master->user_id,
        ]);
    }
}
