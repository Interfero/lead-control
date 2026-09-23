<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySuperPartApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.superpart.api_key');
        $apiSecret = config('services.superpart.api_secret');

        if (app()->environment('production') && (empty($apiKey) || empty($apiSecret))) {
            return response()->json(['message' => 'Интеграция SuperPart не настроена'], 503);
        }

        if (empty($apiKey) || empty($apiSecret)) {
            return $next($request);
        }

        $headerKey = (string) $request->header('X-API-Key', '');
        if (! hash_equals($apiKey, $headerKey)) {
            IntegrationApiLog::create([
                'direction' => 'in',
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'response_code' => 401,
                'request_summary' => 'invalid_api_key',
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Неверный API-ключ'], 401);
        }

        $rawBody = $request->getContent();
        $expectedSig = hash_hmac('sha256', $rawBody, $apiSecret);
        $sign = (string) $request->header('X-Signature', '');

        if (! hash_equals($expectedSig, $sign)) {
            IntegrationApiLog::create([
                'direction' => 'in',
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'response_code' => 401,
                'request_summary' => 'invalid_signature',
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Неверная подпись'], 401);
        }

        return $next($request);
    }
}
