<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventVideoDownload
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // التحقق من أن هذا طلب لبث فيديو
        if ($request->is('api/lessons/*/stream')) {
            // إضافة headers الحماية
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; media-src 'self'");
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, nosnippet, noarchive');
            $response->headers->set('X-Video-Protection', 'enabled');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            
            // منع التخزين المؤقت
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            
            // إجبار المتصفح على عرض الفيديو inline وليس تحميله
            $response->headers->set('Content-Disposition', 'inline');
        }

        return $response;
    }
}
