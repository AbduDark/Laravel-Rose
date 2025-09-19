<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log security-sensitive requests
        if ($this->shouldLog($request)) {
            Log::channel('security')->info('API Request', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'user_id' => auth()->id(),
                'timestamp' => now()->toISOString()
            ]);
        }
        
        return $next($request);
    }
    
    private function shouldLog(Request $request): bool
    {
        $sensitiveRoutes = [
            'login',
            'register',
            'password',
            'admin',
            'payment'
        ];
        
        foreach ($sensitiveRoutes as $route) {
            if (str_contains($request->path(), $route)) {
                return true;
            }
        }
        
        return false;
    }
}
