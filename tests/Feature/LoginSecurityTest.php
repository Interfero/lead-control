<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityAlert;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\GeoIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Журнал входов, последний вход сотрудника и уведомление о входе из Приморского края. */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $code): User
    {
        $role = Role::query()->firstOrCreate(['role_code' => $code], ['role_name' => $code, 'is_active' => true]);
        $user = User::query()->create([
            'user_name' => 'Тест '.$code, 'email' => $code.uniqid().'@example.test', 'password' => 'secret', 'is_active' => true,
        ]);
        $user->roles()->attach($role->role_id);

        return $user->fresh(['roles']);
    }

    private function fakeGeo(?array $geo): void
    {
        $this->app->instance(GeoIpService::class, new class($geo) extends GeoIpService
        {
            public function __construct(private ?array $fixed) {}

            public function lookup(?string $ip): ?array
            {
                return $this->fixed;
            }
        });
    }

    private function ssoLogin(User $user, string $ip = '203.0.113.7'): void
    {
        Cache::put('hub_sso:code1', ['user_id' => $user->user_id], 60);
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->get(route('hub.sso.callback', ['code' => 'code1']));
        auth()->logout();
    }

    public function test_sso_login_is_written_to_journal_with_region(): void
    {
        $this->fakeGeo(['country' => 'RU', 'region' => 'Tula Oblast', 'city' => 'Tula']);
        $user = $this->userWithRole('branch_head');
        $this->ssoLogin($user);

        $log = SecurityAuditLog::query()->where('action', 'login_success')->where('user_id', $user->user_id)->firstOrFail();
        $this->assertSame('hub_sso', $log->meta['via']);
        $this->assertSame('Tula Oblast', $log->meta['region']);
        $this->assertSame(0, SecurityAlert::query()->count());
    }

    public function test_login_from_primorye_creates_alert_once_per_throttle_window(): void
    {
        $this->fakeGeo(['country' => 'RU', 'region' => 'Primorskiy (Maritime) Kray', 'city' => 'Vladivostok']);
        $user = $this->userWithRole('master');
        $this->ssoLogin($user);
        $this->ssoLogin($user);   // второй вход подряд — без повторного уведомления

        $this->assertSame(1, SecurityAlert::query()->where('user_id', $user->user_id)->count());
        $this->assertSame(1, SecurityAuditLog::query()->where('action', 'login_region_alert')->count());
        $this->assertSame(2, SecurityAuditLog::query()->where('action', 'login_success')->count());
        $this->assertStringContainsString('Vladivostok', SecurityAlert::query()->firstOrFail()->message);
    }

    public function test_primorye_spelling_variants_are_detected(): void
    {
        $svc = new GeoIpService;
        $this->assertTrue($svc->isAlertRegion(['region' => 'Primorye']));
        $this->assertTrue($svc->isAlertRegion(['region' => 'Primorskiy (Maritime) Kray']));
        $this->assertFalse($svc->isAlertRegion(['region' => 'Moscow']));
        $this->assertFalse($svc->isAlertRegion(null));
    }

    public function test_missing_geo_database_does_not_break_login(): void
    {
        config(['geoip.database' => '/nonexistent/db.mmdb']);
        $user = $this->userWithRole('branch_head');
        $this->ssoLogin($user);

        $this->assertSame(1, SecurityAuditLog::query()->where('action', 'login_success')->count());
        $this->assertSame(0, SecurityAlert::query()->count());
    }

    public function test_banner_is_visible_to_general_director_only_and_can_be_marked_seen(): void
    {
        $alert = SecurityAlert::query()->create(['type' => 'login_region_alert', 'user_id' => null,
            'message' => 'Вход в CRM из региона «Vladivostok, Primorye»: Иванов (№5)', 'meta' => ['ip' => '1.2.3.4'], 'created_at' => now()]);
        $gd = $this->userWithRole('general_director');
        $head = $this->userWithRole('branch_head');

        $this->actingAs($gd)->get('/crm/orders')->assertSee('Vladivostok, Primorye');
        $this->actingAs($head)->get('/crm/orders')->assertDontSee('Vladivostok, Primorye');
        $this->actingAs($head)->post('/crm/security-alerts/'.$alert->id.'/seen')->assertForbidden();

        $this->actingAs($gd)->post('/crm/security-alerts/'.$alert->id.'/seen')->assertRedirect();
        $this->assertNotNull($alert->fresh()->seen_at);
        $this->actingAs($gd)->get('/crm/orders')->assertDontSee('Vladivostok, Primorye');
    }

    public function test_hr_list_shows_last_login(): void
    {
        $this->fakeGeo(['country' => 'RU', 'region' => 'Tula Oblast', 'city' => 'Tula']);
        $gd = $this->userWithRole('general_director');
        $emp = $this->userWithRole('branch_head');
        $this->ssoLogin($emp, '203.0.113.9');

        $this->actingAs($gd)->get('/crm/hr')->assertOk()->assertSee('Последний вход')->assertSee('Tula, Tula Oblast');
    }
}
