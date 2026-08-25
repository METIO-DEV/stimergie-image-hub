<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMonitoringToken
{
    /**
     * Restrict operational endpoints to the monitoring collector.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('monitoring.token');
        $providedToken = $request->header('X-Monitoring-Token');

        abort_unless(
            is_string($expectedToken)
            && $expectedToken !== ''
            && is_string($providedToken)
            && hash_equals($expectedToken, $providedToken),
            403,
        );

        return $next($request);
    }
}
