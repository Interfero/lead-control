<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Именованные лимитеры (throttle:client-cards) без бага Laravel
 * func_num_args() === 3, из‑за которого route:cache даёт 500.
 */
class ThrottleRequestsFixed extends ThrottleRequests
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (is_string($maxAttempts) && ! is_null($limiter = $this->limiter->limiter($maxAttempts))) {
            return $this->handleRequestUsingNamedLimiter($request, $next, $maxAttempts, $limiter);
        }

        return $this->handleRequest(
            $request,
            $next,
            [
                (object) [
                    'key' => $prefix.$this->resolveRequestSignature($request),
                    'maxAttempts' => $this->resolveMaxAttempts($request, $maxAttempts),
                    'decaySeconds' => 60 * $decayMinutes,
                    'afterCallback' => null,
                    'responseCallback' => null,
                ],
            ]
        );
    }
}
