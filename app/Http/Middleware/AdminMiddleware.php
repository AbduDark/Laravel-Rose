<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class AdminMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return $this->unauthorizedResponse();
        }

        if (!auth()->user()->isAdmin()) {
            return $this->forbiddenResponse();
        }

        return $next($request);
    }
}
