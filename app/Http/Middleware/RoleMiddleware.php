<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class RoleMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next, $role)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'status_code' => 401,
                'message' => [
                    'ar' => 'غير مصرح - يجب تسجيل الدخول',
                    'en' => 'Unauthorized - Please login'
                ]
            ], 401);
        }

        $user = auth()->user();

        // التحقق من الدور
        if ($role === 'admin' && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'status_code' => 403,
                'message' => [
                    'ar' => 'ممنوع - ليس لديك صلاحية للوصول',
                    'en' => 'Forbidden - You do not have permission'
                ]
            ], 403);
        }

        return $next($request);
    }
}