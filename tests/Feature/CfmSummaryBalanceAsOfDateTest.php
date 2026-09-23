<?php

namespace Tests\Feature;

use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\CfmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfmSummaryBalanceAsOfDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_remainder_depends_on_end_date_not_start_date(): void
    {
        $city = City::query()->create([
            'city_name' => 'Тест-касса',
            'city_timezone' => 'Europe/Moscow',
            'city_type' => 'city',
        ]);
        $income = CfmCategory::query()->create([
            'cfm_cat_name' => 'Поступление с Заказов',
            'cfm_cat_group' => 'inflows',
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => true,
        ]);
        $out = CfmCategory::query()->create([
            'cfm_cat_name' => 'Возвраты клиентам',
            'cfm_cat_group' => 'outflows',
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => false,
        ]);
        $author = User::query()->create([
            'user_name' => 'CFM summary tester',
            'email' => 'cfm-summary-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $author->roles()->attach(Role::query()->firstOrCreate(
            ['role_code' => 'developer'],
            ['role_name' => 'developer', 'is_active' => true],
        )->role_id);

        $this->op($city->city_id, $income->cfm_cat_id, $author->user_id, 10000, '2026-08-10 12:00:00');
        $this->op($city->city_id, $income->cfm_cat_id, $author->user_id, 1000, '2026-09-05 12:00:00');
        $this->op($city->city_id, $out->cfm_cat_id, $author->user_id, 200, '2026-09-06 12:00:00');
        $other = CfmCategory::query()->create([
            'cfm_cat_name' => 'Прочее Поступление',
            'cfm_cat_group' => 'inflows',
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => false,
        ]);
        $this->op($city->city_id, $other->cfm_cat_id, $author->user_id, 300, '2026-08-20 12:00:00');

        $svc = app(CfmService::class);
        $ids = [(int) $city->city_id];
        $sep = $svc->getSummaryReport($ids, '2026-09-01', '2026-09-14', collect([$city]));
        $jul = $svc->getSummaryReport($ids, '2026-07-01', '2026-09-14', collect([$city]));

        $this->assertSame(1000, (int) $sep['cities'][$city->city_id]['order_income']);
        $this->assertSame(200, (int) $sep['cities'][$city->city_id]['total_outflows']);
        $this->assertSame(0, (int) $sep['cities'][$city->city_id]['other_inflows']);
        $this->assertSame(300, (int) $jul['cities'][$city->city_id]['other_inflows']);

        $this->assertSame(11100, (int) $sep['cities'][$city->city_id]['balance']);
        $this->assertSame(11100, (int) $jul['cities'][$city->city_id]['balance']);
        $this->assertSame(11100, (int) $sep['totals']['balance']);
        $this->assertSame(11100, (int) $jul['totals']['balance']);
    }

    private function op(int $cityId, int $catId, int $userId, int $amount, string $closedAt): void
    {
        $op = new CfmOperation([
            'city_id' => $cityId,
            'cfm_cat_id' => $catId,
            'amount_cfm' => $amount,
            'cfm_created_at' => $closedAt,
            'cfm_closed_at' => $closedAt,
        ]);
        $op->cfm_created_by = $userId;
        $op->cfm_closed_by = $userId;
        $op->save();
    }
}
