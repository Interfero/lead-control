<?php

namespace Tests\Feature;

use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Role;
use App\Models\SalaryCalculation;
use App\Models\User;
use App\Services\CfmService;
use App\Services\DirectorSalaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncassationPostingDateAndSatellitePoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Europe/Moscow'));
        config(['cfm.overdraft_limit' => 10_000_000]); // лимит минуса кассы здесь не предмет проверки
        CfmCategory::query()->firstOrCreate(['cfm_cat_name' => DirectorSalaryService::PAYMENT_CATEGORY], [
            'cfm_cat_group' => 'outflows', 'cfm_cat_activities' => 'operating',
            'is_visible' => true, 'is_auto' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_incassation_can_be_posted_with_last_moment_of_previous_month(): void
    {
        $svc = app(CfmService::class);
        $op = $this->draftIncas($this->city('Курск'), 200_000);

        $closed = $svc->close($op, $this->user('branch_head')->user_id, $svc->previousMonthEnd());

        $this->assertSame('2026-10-31 23:59:59', $closed->cfm_closed_at->format('Y-m-d H:i:s'));
    }

    public function test_other_dates_are_rejected(): void
    {
        $svc = app(CfmService::class);
        $op = $this->draftIncas($this->city('Курск'), 1000);

        $this->expectException(ValidationException::class);
        $svc->close($op, $this->user('branch_head')->user_id, Carbon::parse('2026-03-31 23:59:59', 'Europe/Moscow'));
    }

    public function test_non_incassation_cannot_be_backdated(): void
    {
        $svc = app(CfmService::class);
        $cat = CfmCategory::query()->create([
            'cfm_cat_name' => 'Расход прочий', 'cfm_cat_group' => 'inflows',
            'cfm_cat_activities' => 'operating', 'is_visible' => true, 'is_auto' => false,
        ]);
        $op = $this->op($this->city('Курск'), $cat, 1000);

        $this->expectException(ValidationException::class);
        $svc->close($op, $this->user('branch_head')->user_id, $svc->previousMonthEnd());
    }

    public function test_close_endpoint_posts_to_previous_month_and_default_is_now(): void
    {
        $head = $this->user('branch_head');
        $city = $this->city('Курск');
        $head->cities()->attach($city->city_id);
        $a = $this->draftIncas($city, 1000);
        $b = $this->draftIncas($city, 2000);

        $this->actingAs($head)->post(route('cfm.close', $a->cfm_id), ['posting' => 'prev_month_end']);
        $this->actingAs($head)->post(route('cfm.close', $b->cfm_id));
        $c = $this->draftIncas($city, 3000);
        $this->actingAs($head)->post(route('cfm.close', $c->cfm_id), ['posting' => 'month_end']);

        $this->assertSame('2026-10-31 23:59:59', $a->fresh()->cfm_closed_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02', $b->fresh()->cfm_closed_at->format('Y-m-d'));
        $this->assertSame('2026-11-30 23:59:59', $c->fresh()->cfm_closed_at->format('Y-m-d H:i:s'));
    }

    public function test_show_page_offers_posting_date_only_for_open_incassation(): void
    {
        $head = $this->user('branch_head');
        $city = $this->city('Курск');
        $head->cities()->attach($city->city_id);
        $op = $this->draftIncas($city, 1000);

        $this->actingAs($head)->get(route('cfm.show', $op->cfm_id))
            ->assertOk()->assertSee('Дата проводки')->assertSee('По умолчанию (текущий момент)')
            ->assertSee('Октябрь 31.10.2026')->assertSee('Ноябрь 30.11.2026');

        app(CfmService::class)->close($op, $head->user_id);
        $this->actingAs($head)->get(route('cfm.show', $op->cfm_id))
            ->assertOk()->assertDontSee('Дата проводки');
    }

    public function test_director_can_withdraw_only_what_was_handed_over_in_that_city(): void
    {
        $svc = app(DirectorSalaryService::class);
        $kursk = $this->city('Курск');
        $zhel = $this->city('Железногорск', $kursk);
        $this->closedIncas($kursk, 200_000, '2026-10-15 10:00:00');
        $this->closedIncas($zhel, 60_000, '2026-10-20 10:00:00');
        $head = $this->user('branch_head');
        $head->cities()->attach([$kursk->city_id, $zhel->city_id]);

        $k = $svc->payoutCapSnapshot((int) $kursk->city_id, $head);
        $z = $svc->payoutCapSnapshot((int) $zhel->city_id, $head);

        $this->assertSame(50_000, $k['available']);
        $this->assertSame(15_000, $z['available']);
    }

    public function test_any_city_director_can_pay_salary_even_if_calculation_belongs_to_another(): void
    {
        $svc = app(DirectorSalaryService::class);
        $tula = $this->city('Тула');
        $this->closedIncas($tula, 100_000, '2026-10-15 10:00:00');
        $old = $this->user('branch_head');
        $old->cities()->attach($tula->city_id);
        $svc->payoutCapSnapshot((int) $tula->city_id, $old); // расчёт закреплён за $old
        $old->update(['is_active' => false]);

        $new = $this->user('branch_head');
        $new->cities()->attach($tula->city_id);
        $snap = $svc->payoutCapSnapshot((int) $tula->city_id, $new);
        $this->assertGreaterThan(0, $snap['available']);

        $stranger = $this->user('branch_head');
        // без привязки к городу (static-кеш City::operationGroupIds в тестах путает id)
        try {
            $svc->payoutCapSnapshot((int) $tula->city_id, $stranger);
            $this->fail('Директор чужого города не должен иметь доступ');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_payout_amount_limit_is_off_by_default_and_can_be_enabled(): void
    {
        $svc = app(DirectorSalaryService::class);
        $city = $this->city('Тула');
        $this->closedIncas($city, 100_000, '2026-10-15 10:00:00');
        $head = $this->user('branch_head');
        $head->cities()->attach($city->city_id);
        $snap = $svc->payoutCapSnapshot((int) $city->city_id, $head);

        $pay = $this->op($city, CfmCategory::query()->where('cfm_cat_name', DirectorSalaryService::PAYMENT_CATEGORY)->first(), 5_000_000);
        $pay->salary_calculation_id = $snap['salary_calculation_id'];
        $pay->save();

        // по умолчанию: любая сумма проходит (нет исключения)
        $svc->assertPayoutAllowed($pay);
        $this->assertTrue(true);

        config(['cfm.director_payout_cap_enforced' => true]);
        $this->expectException(ValidationException::class);
        $svc->assertPayoutAllowed($pay);
    }

    public function test_satellite_incas_goes_to_common_pool_but_commission_is_per_city(): void
    {
        $svc = app(DirectorSalaryService::class);
        $kursk = $this->city('Курск');
        $zhel = $this->city('Железногорск', $kursk);
        $period = Carbon::parse('2026-10-01', 'Europe/Moscow');
        $this->closedIncas($kursk, 200_000, '2026-10-15 10:00:00');
        $this->closedIncas($zhel, 60_000, '2026-10-20 10:00:00');

        $k = $svc->ensureCalculation((int) $kursk->city_id, $period);
        $z = $svc->ensureCalculation((int) $zhel->city_id, $period);

        $this->assertSame(25, $k->rate_percent);
        $this->assertSame(50_000, $k->commission_amount);
        $this->assertSame(25, $z->rate_percent);
        $this->assertSame(15_000, $z->commission_amount);
        $this->assertSame(60_000, $z->incas_base);
    }

    public function test_incas_of_satellite_marks_fixed_calcs_of_whole_cluster_for_recalc(): void
    {
        $svc = app(DirectorSalaryService::class);
        $kursk = $this->city('Курск');
        $zhel = $this->city('Железногорск', $kursk);
        $this->closedIncas($kursk, 50_000, '2026-10-15 10:00:00');
        $calc = $svc->ensureCalculation((int) $kursk->city_id, Carbon::parse('2026-10-01', 'Europe/Moscow'));
        $this->assertSame(SalaryCalculation::STATUS_FIXED, $calc->status);

        $svc->markNeedsRecalcForCityDate((int) $zhel->city_id, Carbon::parse('2026-10-31 23:59:59', 'Europe/Moscow'));

        $this->assertSame(SalaryCalculation::STATUS_NEEDS_RECALC, $calc->fresh()->status);
    }

    // ---- helpers ----

    private function city(string $name, ?City $parent = null): City
    {
        return City::query()->create([
            'city_name' => $name.' '.uniqid(), 'city_timezone' => 'Europe/Moscow',
            'city_type' => 'city', 'parent_city_id' => $parent?->city_id,
        ]);
    }

    private function incasCategory(): CfmCategory
    {
        return CfmCategory::query()->firstOrCreate(['cfm_cat_name' => 'Инкассация'], [
            'cfm_cat_group' => 'inflows', 'cfm_cat_activities' => 'operating',
            'is_visible' => true, 'is_auto' => false,
        ]);
    }

    private function op(City $city, CfmCategory $cat, int $amount): CfmOperation
    {
        $op = new CfmOperation([
            'city_id' => $city->city_id, 'cfm_cat_id' => $cat->cfm_cat_id,
            'amount_cfm' => $amount, 'cfm_created_at' => now(),
        ]);
        $op->cfm_created_by = $this->user('developer')->user_id;
        $op->save();

        return $op;
    }

    private function draftIncas(City $city, int $amount): CfmOperation
    {
        return $this->op($city, $this->incasCategory(), $amount);
    }

    private function closedIncas(City $city, int $amount, string $at): CfmOperation
    {
        $op = $this->draftIncas($city, $amount);
        $op->cfm_closed_by = $op->cfm_created_by;
        $op->cfm_closed_at = Carbon::parse($at, 'Europe/Moscow');
        $op->save();

        return $op;
    }

    private function user(string $code): User
    {
        $user = User::query()->create([
            'user_name' => 'Тест '.$code, 'email' => $code.'-'.uniqid('', true).'@example.test',
            'password' => 'secret', 'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->firstOrCreate(
            ['role_code' => $code], ['role_name' => $code, 'is_active' => true],
        )->role_id);

        return $user->fresh(['roles']);
    }
}
