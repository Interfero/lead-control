<?php

namespace Tests\Feature;

use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\CfmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CfmOverdraftLimitTest extends TestCase
{
    use RefreshDatabase;

    private CfmService $cfm;

    private User $author;

    private CfmCategory $outflow;

    private CfmCategory $inflow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cfm.overdraft_limit' => 2000]);
        $this->cfm = app(CfmService::class);
        $this->author = $this->userWithRole('developer');
        $this->outflow = $this->category('Инкассация', 'outflows');
        $this->inflow = $this->category('Поступление с Заказов', 'inflows');
    }

    public function test_close_outflow_exactly_2000_on_empty_till_is_allowed(): void
    {
        $city = $this->city('Основа');
        $op = $this->draftOutflow($city, 2000);

        $closed = $this->cfm->close($op, $this->author->user_id);

        $this->assertNotNull($closed->cfm_closed_at);
        $this->assertSame(-2000, $this->cfm->closedBalanceForCity((int) $city->city_id));
    }

    public function test_close_outflow_2001_on_empty_till_is_blocked(): void
    {
        $city = $this->city('Основа');
        $op = $this->draftOutflow($city, 2001);

        try {
            $this->cfm->close($op, $this->author->user_id);
            $this->fail('Ожидали отказ по лимиту минуса');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->implode(' ');
            $this->assertStringContainsString('ниже −2', $msg);
            $this->assertStringContainsString('отдельно', $msg);
        }

        $this->assertNull($op->fresh()->cfm_closed_at);
        $this->assertSame(0, $this->cfm->closedBalanceForCity((int) $city->city_id));
    }

    public function test_parent_and_satellite_tills_are_independent(): void
    {
        $parent = $this->city('НН основа');
        $satellite = $this->city('Дзержинск', $parent);

        $this->closedOp($satellite, $this->inflow, 10000);

        $blocked = $this->draftOutflow($parent, 2001);
        try {
            $this->cfm->close($blocked, $this->author->user_id);
            $this->fail('Касса основы не должна смотреть на остаток спутника');
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->implode(' ');
            $this->assertStringContainsString('город', $msg);
            $this->assertStringContainsString($parent->city_name, $msg);
        }
        $this->assertNull($blocked->fresh()->cfm_closed_at);

        $ok = $this->draftOutflow($satellite, 3000);
        $closed = $this->cfm->close($ok, $this->author->user_id);
        $this->assertNotNull($closed->cfm_closed_at);
        $this->assertSame(7000, $this->cfm->closedBalanceForCity((int) $satellite->city_id));
        $this->assertSame(0, $this->cfm->closedBalanceForCity((int) $parent->city_id));
    }

    public function test_second_outflow_blocked_when_already_minus_1500(): void
    {
        $city = $this->city('Основа');
        $this->cfm->close($this->draftOutflow($city, 1500), $this->author->user_id);

        $next = $this->draftOutflow($city, 600);
        $this->expectException(ValidationException::class);
        $this->cfm->close($next, $this->author->user_id);
    }

    public function test_inflow_is_not_limited_even_when_till_already_below_floor(): void
    {
        $city = $this->city('Основа');
        $this->closedOp($city, $this->outflow, 5000);

        $in = $this->draft($city, $this->inflow, 100);
        $closed = $this->cfm->close($in, $this->author->user_id);

        $this->assertNotNull($closed->cfm_closed_at);
        $this->assertSame(-4900, $this->cfm->closedBalanceForCity((int) $city->city_id));
    }

    public function test_open_draft_does_not_count_toward_remainder(): void
    {
        $city = $this->city('Основа');
        $this->draftOutflow($city, 9000);
        $ok = $this->draftOutflow($city, 2000);

        $closed = $this->cfm->close($ok, $this->author->user_id);

        $this->assertNotNull($closed->cfm_closed_at);
        $this->assertSame(-2000, $this->cfm->closedBalanceForCity((int) $city->city_id));
    }

    public function test_http_close_flashes_russian_error_and_keeps_operation_open(): void
    {
        $city = $this->city('Основа');
        $op = $this->draftOutflow($city, 2500);

        $this->actingAs($this->author)
            ->from(route('cfm.show', $op->cfm_id))
            ->post(route('cfm.close', $op->cfm_id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $error = session('error');
        $this->assertIsString($error);
        $this->assertStringContainsString('Касса', $error);
        $this->assertStringContainsString('отдельно', $error);
        $this->assertNull($op->fresh()->cfm_closed_at);
    }

    public function test_show_page_names_satellite_till_and_warns_on_breach(): void
    {
        $parent = $this->city('НН основа');
        $satellite = $this->city('Дзержинск', $parent);
        $op = $this->draftOutflow($satellite, 2500);

        $this->actingAs($this->author)
            ->get(route('cfm.show', $op->cfm_id))
            ->assertOk()
            ->assertSee('спутник', false)
            ->assertSee($satellite->city_name, false)
            ->assertSee('Кассы города и спутника', false);
    }

    private function city(string $prefix, ?City $parent = null): City
    {
        return City::query()->create([
            'city_name' => $prefix.' '.uniqid(),
            'city_timezone' => 'Europe/Moscow',
            'city_type' => 'city',
            'parent_city_id' => $parent?->city_id,
        ]);
    }

    private function category(string $name, string $group): CfmCategory
    {
        return CfmCategory::query()->create([
            'cfm_cat_name' => $name.' '.uniqid(),
            'cfm_cat_group' => $group,
            'cfm_cat_activities' => 'operating',
            'is_visible' => true,
            'is_auto' => $group === 'inflows',
        ]);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::query()->create([
            'user_name' => 'CFM overdraft '.$roleCode,
            'email' => $roleCode.'-overdraft-'.uniqid('', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->firstOrCreate(
            ['role_code' => $roleCode],
            ['role_name' => $roleCode, 'is_active' => true],
        )->role_id);

        return $user->fresh(['roles']);
    }

    private function draftOutflow(City $city, int $amount): CfmOperation
    {
        return $this->draft($city, $this->outflow, $amount);
    }

    private function draft(City $city, CfmCategory $cat, int $amount): CfmOperation
    {
        $op = new CfmOperation([
            'city_id' => $city->city_id,
            'cfm_cat_id' => $cat->cfm_cat_id,
            'amount_cfm' => $amount,
            'cfm_adds' => 'Тест лимита',
            'cfm_created_at' => now(),
        ]);
        $op->cfm_created_by = $this->author->user_id;
        $op->save();

        return $op->fresh(['category', 'city.parentCity']);
    }

    private function closedOp(City $city, CfmCategory $cat, int $amount): CfmOperation
    {
        $op = $this->draft($city, $cat, $amount);
        $op->cfm_closed_by = $this->author->user_id;
        $op->cfm_closed_at = now();
        $op->save();

        return $op->fresh();
    }
}
