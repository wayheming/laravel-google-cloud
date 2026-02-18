<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCloudScheduler
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $token = $request->header('X-CloudScheduler-Token');

        if (! $token || $token !== config('services.cloud_scheduler.token')) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
