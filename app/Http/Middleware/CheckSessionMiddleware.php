<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class CheckSessionMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('sanctum')->check()) {
            return response()->json([
                'success' => false,
                'message' => [
                    'ar' => 'يرجى تسجيل الدخول للوصول إلى هذا المحتوى',
                    'en' => 'Please login to access this content'
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::guard('sanctum')->user();

        // Check if user session is still valid
        if ($user->session_id && $user->session_id !== session()->getId()) {
            Auth::guard('sanctum')->logout();
            return $this->errorResponse([
                'ar' => 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول مرة أخرى',
                'en' => 'Session expired. Please login again'
            ], 401);
        }

        return $next($request);
    }
}