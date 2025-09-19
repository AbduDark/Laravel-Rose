<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
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

        // Check if user has active subscription
        if (!$user->subscriptions()->where('expires_at', '>', now())->exists()) {
            return $this->errorResponse([
                'ar' => 'يجب أن يكون لديك اشتراك نشط للوصول إلى هذا المحتوى',
                'en' => 'You need an active subscription to access this content'
            ], 403);
        }

        return $next($request);
    }
}