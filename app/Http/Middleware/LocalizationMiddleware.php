<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocalizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language', 'ar');
        
        // Validate locale
        $supportedLocales = ['ar', 'en'];
        if (!in_array($locale, $supportedLocales)) {
            $locale = 'ar'; // Default to Arabic
        }
        
        App::setLocale($locale);
        
        return $next($request);
    }
}
