<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckRoleMiddlewareTest extends TestCase
{
    public function test_branch_head_allowed_for_complaints_view_roles(): void
    {
        $user = $this->mockUser(['branch_head']);
        $mw = new CheckRole;
        $request = Request::create('/complaints', 'GET');
        $request->setUserResolver(fn () => $user);

        $passed = false;
        $mw->handle($request, function () use (&$passed) {
            $passed = true;

            return response('ok');
        }, 'developer', 'call_center', 'senior_dispatcher', 'senior_manager', 'branch_head', 'regional_director', 'general_director');

        $this->assertTrue($passed);
    }

    public function test_branch_head_denied_for_create_only_roles(): void
    {
        $user = $this->mockUser(['branch_head']);
        $mw = new CheckRole;
        $request = Request::create('/complaints/create', 'GET');
        $request->setUserResolver(fn () => $user);

        try {
            $mw->handle($request, fn () => response('ok'), 'developer', 'call_center');
            $this->fail('Expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /** @param  list<string>  $roles */
    private function mockUser(array $roles): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturnUsing(fn (string $code) => in_array($code, $roles, true));
        $user->shouldReceive('hasAnyRole')->andReturnUsing(
            fn (array $codes) => count(array_intersect($roles, $codes)) > 0
        );

        return $user;
    }
}
