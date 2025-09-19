<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Traits\ApiResponseTrait;

class RateLimitMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->errorResponse([
                'ar' => 'تم تجاوز الحد المسموح من الطلبات. حاول مرة أخرى لاحقاً',
                'en' => 'Too many requests. Please try again later'
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }

    protected function resolveRequestSignature($request)
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }
}
