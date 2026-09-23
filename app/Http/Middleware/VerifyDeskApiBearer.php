<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeskApiBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = trim((string) config('services.desk_api.bearer_token', ''));
        if ($configuredToken === '') {
            return $this->errorResponse('Desk API bearer token is not configured.', 503);
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        if ($authorization === '' || ! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return $this->errorResponse('Missing Bearer token.', 401);
        }

        $providedToken = trim((string) ($matches[1] ?? ''));
        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return $this->errorResponse('Invalid Bearer token.', 401);
        }

        return $next($request);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
