<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpsHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsHealthController extends Controller
{
    public function __invoke(Request $request, OpsHealthService $health): JsonResponse
    {
        $token = (string) config('security.ops_health_token', '');
        if ($token !== '') {
            $given = (string) $request->header('X-Ops-Token', $request->query('token', ''));
            if (! hash_equals($token, $given)) {
                return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
            }
        }

        $snapshot = $health->snapshot();

        return response()->json($snapshot, $snapshot['ok'] ? 200 : 503);
    }
}
