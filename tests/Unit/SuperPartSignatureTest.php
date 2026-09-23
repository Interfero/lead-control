<?php

namespace Tests\Unit;

use App\Http\Middleware\VerifySuperPartApi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperPartSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('integration_api_logs');
        Schema::create('integration_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction')->nullable();
            $table->string('channel')->nullable();
            $table->string('method')->nullable();
            $table->text('url')->nullable();
            $table->unsignedInteger('response_code')->nullable();
            $table->text('request_summary')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();
        });

        config([
            'services.superpart.api_key' => 'test-key',
            'services.superpart.api_secret' => 'test-secret',
        ]);
    }

    public function test_valid_signature_passes(): void
    {
        $body = '{"order_id":1}';
        $sig = hash_hmac('sha256', $body, 'test-secret');
        $request = Request::create('/api/v1/test', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => 'test-key',
            'HTTP_X_SIGNATURE' => $sig,
        ], $body);

        $mw = new VerifySuperPartApi;
        $response = $mw->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_invalid_signature_rejected(): void
    {
        $body = '{"order_id":1}';
        $request = Request::create('/api/v1/test', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => 'test-key',
            'HTTP_X_SIGNATURE' => 'deadbeef',
        ], $body);

        $mw = new VerifySuperPartApi;
        $response = $mw->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(401, $response->getStatusCode());
    }
}
