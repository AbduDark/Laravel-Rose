<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

trait ApiResponseTrait
{
    public function successResponse($data = null, $message = 'Success', $status = 200): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => true,
            'status_code' => $status,
            'message' => [
                'ar' => $message,
                'en' => $message
            ],
            'data' => $data
        ], $status);
    }

    public function errorResponse($message = 'Error', $status = 400, $errors = null): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        $response = [
            'success' => false,
            'status_code' => $status,
            'message' => [
                'ar' => is_array($message) ? ($message['ar'] ?? 'خطأ') : 'خطأ',
                'en' => is_array($message) ? ($message['en'] ?? 'Error') : 'Error'
            ]
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    public function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 422,
            'message' => [
                'ar' => 'بيانات غير صحيحة',
                'en' => 'Validation failed'
            ],
            'errors' => $exception->errors()
        ], 422);
    }

    public function notFoundResponse(): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 404,
            'message' => [
                'ar' => 'المورد غير موجود',
                'en' => 'Resource not found'
            ]
        ], 404);
    }

    public function unauthorizedResponse(): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 401,
            'message' => [
                'ar' => 'غير مصرح لك بالوصول - يجب تسجيل الدخول',
                'en' => 'Unauthorized access - Login required'
            ]
        ], 401);
    }

    public function forbiddenResponse(): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 403,
            'message' => [
                'ar' => 'ممنوع - ليس لديك صلاحية للوصول',
                'en' => 'Forbidden - You do not have permission'
            ]
        ], 403);
    }

    public function serverErrorResponse(): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 500,
            'message' => [
                'ar' => 'خطأ في الخادم الداخلي',
                'en' => 'Internal server error'
            ]
        ], 500);

    }

 public function subscriptionRequiredResponse(): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 403,
            'message' => [
                'ar' => 'يجب الاشتراك في الدورة للوصول إلى هذا المحتوى',
                'en' => 'Subscription required to access this content'
            ],
            'subscription_required' => true
        ], 403);
    }

    public function expiredSubscriptionResponse(): JsonResponse
    {
        $locale = request()->header('Accept-Language', 'ar');
        $locale = in_array($locale, ['ar', 'en']) ? $locale : 'ar';

        return response()->json([
            'success' => false,
            'status_code' => 403,
            'message' => [
                'ar' => 'انتهت صلاحية اشتراكك في هذه الدورة',
                'en' => 'Your subscription to this course has expired'
            ],
            'subscription_expired' => true
        ], 403);
    }
}





   
