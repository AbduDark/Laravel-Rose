<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Resources\LessonResource;

class LessonController extends Controller
{
    use ApiResponseTrait;

    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum');
    // }

    /**
     * Get all lessons with pagination for public access
     */
    public function publicIndex(Request $request)
    {
        try {
            $query = Lesson::with(['course'])
                ->orderBy('order', 'asc')
                ->orderBy('created_at', 'desc');

            // Search functionality
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by course
            if ($request->has('course_id')) {
                $query->where('course_id', $request->get('course_id'));
            }

            // Filter by free lessons only for non-authenticated users
            $user = $request->user();
            if (!$user || !$user->isAdmin()) {
                $query->where('is_free', true);
            }

            $lessons = $query->paginate(10);

            return LessonResource::collection($lessons)->additional([
                'message' => [
                    'ar' => 'تم جلب الدروس بنجاح',
                    'en' => 'Lessons retrieved successfully'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Public get lessons error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get all lessons for admin
     */
    public function adminIndex(Request $request)
    {
        try {
            // التحقق من صلاحيات المدير
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بالوصول', 403);
            }

            $query = Lesson::with(['course']);

            // Search functionality
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by course
            if ($request->has('course_id')) {
                $query->where('course_id', $request->get('course_id'));
            }

            // Filter by gender
            if ($request->has('target_gender')) {
                $query->where('target_gender', $request->get('target_gender'));
            }

            // Filter by video status
            if ($request->has('video_status')) {
                $query->where('video_status', $request->get('video_status'));
            }

            $lessons = $query->orderBy('order', 'asc')
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 15));

            return $this->successResponse($lessons, [
                'ar' => 'تم جلب جميع الدروس بنجاح',
                'en' => 'All lessons retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Admin get lessons error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get lessons for a specific course
     */
    public function index($courseId, Request $request)
    {
        try {
            $course = Course::findOrFail($courseId);
            $user = $request->user();

            if (!$user) {
                return $this->errorResponse('يجب تسجيل الدخول لعرض الدروس', 401);
            }

            // التحقق من حالة الكورس
// بعد التعديل (سيظهر الرسالة المحددة):
            if (!$course->is_active) {
                 return $this->errorResponse([
                     'ar' => 'هذه الدورة غير متاحة حالياً',
                       'en' => 'This course is currently unavailable'
                ], 403);
            }

            // التحقق من توافق الجنس
            // if ($course->target_gender !== 'both' && $course->target_gender !== $user->gender) {
            //     return $this->errorResponse('هذه الدورة غير متاحة لجنسك', 403);
            // }

            // التحقق من الاشتراك النشط
            $isSubscribed = $user->canAccessCourse($courseId);

            if (!$isSubscribed) {
                // إرجاع الدروس المجانية فقط للمستخدمين غير المشتركين
                $lessons = $course->lessons()
                    ->where('is_free', true)
                    ->where(function($query) use ($user) {
                        $query->where('target_gender', 'both')
                              ->orWhere('target_gender', $user->gender);
                    })
                    ->orderBy('order')
                    ->get();

                // إضافة رسالة توضيحية
                $message = 'تم جلب الدروس المجانية فقط. يجب الاشتراك في الدورة للوصول لجميع الدروس';
            } else {
                // إرجاع جميع الدروس للمشتركين والمديرين
                $lessons = $course->lessons()
                    ->where(function($query) use ($user) {
                        $query->where('target_gender', 'both')
                              ->orWhere('target_gender', $user->gender);
                    })
                    ->orderBy('order')
                    ->get();

                $message = 'تم جلب الدروس بنجاح';
            }

            // إضافة معلومات إضافية للدروس
            $lessons->each(function($lesson) use ($user, $isSubscribed) {
                // إضافة رابط الفيديو إذا كان المستخدم يستطيع الوصول للدرس
                if ($lesson->can_access && $lesson->has_video) {
                    $lesson->video_url = $lesson->getVideoDirectUrl();
                    $lesson->video_duration_formatted = $lesson->getFormattedDuration();
                    $lesson->video_size_formatted = $lesson->getFormattedSize();
                } else {
                    $lesson->video_url = null;
                }
            });

            // الحصول على معلومات الاشتراك النشط
            $activeSubscription = $user->getActiveSubscription($courseId);

            return $this->successResponse([
                'course' => $course,
                'lessons' => $lessons,
                'user_subscribed' => $isSubscribed,
                'subscription_info' => $activeSubscription ? [
                    'expires_at' => $activeSubscription->expires_at,
                    'days_remaining' => $activeSubscription->getDaysRemaining()
                ] : null
            ], $message);

        } catch (\Exception $e) {
            Log::error('Get lessons error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Store a new lesson
     */
    public function store(Request $request)
    {
        try {
            // التحقق من صلاحيات المدير
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بإنشاء الدروس', 403);
            }

            $request->validate([
                'course_id' => 'required|exists:courses,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'content' => 'required|string',
                'order' => 'nullable|integer|min:0',
                'duration_minutes' => 'nullable|integer|min:0',
                'is_free' => 'boolean',
                'target_gender' => 'required|in:male,female,both',
                'is_video_protected' => 'boolean',
            ]);

            $lesson = Lesson::create(array_merge(
                $request->all(),
                ['is_video_protected' => $request->get('is_video_protected', true)]
            ));

            return $this->successResponse(
                $lesson->load('course'),
                'تم إنشاء الدرس بنجاح',
                201
            );

        } catch (\Exception $e) {
            Log::error('Create lesson error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Update lesson
     */
    public function update(Request $request, $id)
    {
        try {
            // التحقق من صلاحيات المدير
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بتعديل الدروس', 403);
            }

            $lesson = Lesson::findOrFail($id);

            $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'content' => 'sometimes|string',
                'order' => 'nullable|integer|min:0',
                'duration_minutes' => 'nullable|integer|min:0',
                'is_free' => 'boolean',
                'target_gender' => 'sometimes|in:male,female,both',
                'is_video_protected' => 'boolean',
            ]);

            $lesson->update($request->all());

            return $this->successResponse(
                $lesson->load('course'),
                'تم تحديث الدرس بنجاح'
            );

        } catch (\Exception $e) {
            Log::error('Update lesson error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Delete lesson
     */
    public function destroy($id)
    {
        try {
            // التحقق من صلاحيات المدير
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بحذف الدروس', 403);
            }

            $lesson = Lesson::findOrFail($id);

            // حذف ملف الفيديو إذا وجد
            $lesson->deleteVideoFile();

            $lesson->delete();

            return $this->successResponse([], 'تم حذف الدرس بنجاح');

        } catch (\Exception $e) {
            Log::error('Delete lesson error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Show single lesson
     */
    public function show($id)
    {
        try {
            /** @var User $user */
            $user = auth()->user();

            if (!$user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً', 401);
            }

            $lesson = Lesson::with(['course', 'comments.user'])->find($id);

            if (!$lesson) {
                return $this->errorResponse('الدرس غير موجود', 404);
            }

            // التحقق من توافق الجنس
            if ($lesson->target_gender !== 'both' && $lesson->target_gender !== $user->gender) {
    return $this->errorResponse([
        'ar' => 'هذا الدرس غير متاح لجنسك',
        'en' => 'This lesson is not available for your gender'
    ], 403);
}

if (!$lesson->course->is_active) {
    return $this->errorResponse([
        'ar' => 'هذه الدورة غير نشطة',
        'en' => 'This course is not active'
    ], 403);
}

            // التحقق من الاشتراك إذا لم يكن الدرس مجاني
            $canAccess = $lesson->is_free || $user->canAccessCourse($lesson->course_id);

            if (!$canAccess) {
                return $this->subscriptionRequiredResponse();
            }

            // إضافة معلومات الوصول للفيديو
            if ($lesson->has_video && $canAccess) {
                $lesson->video_url = $lesson->getVideoDirectUrl();
                $lesson->video_duration_formatted = $lesson->getFormattedDuration();
                $lesson->video_size_formatted = $lesson->getFormattedSize();
                $lesson->video_status_message = $lesson->getVideoStatusMessage();
            } else {
                $lesson->video_url = null;
            }

            return $this->successResponse($lesson, 'تم جلب الدرس بنجاح');

        } catch (\Exception $e) {
            Log::error('Error retrieving lesson: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * Get lesson video information for admin
     */
    public function getVideoInfo($id)
    {
        try {
            if (!auth()->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بالوصول', 403);
            }

            $lesson = Lesson::findOrFail($id);

            $videoInfo = [
                'lesson_id' => $lesson->id,
                'has_video' => $lesson->hasVideo(),
                'video_status' => $lesson->video_status,
                'video_path' => $lesson->video_path,
                'video_duration' => $lesson->video_duration,
                'video_size' => $lesson->video_size,
                'video_duration_formatted' => $lesson->getFormattedDuration(),
                'video_size_formatted' => $lesson->getFormattedSize(),
                'is_video_protected' => $lesson->is_video_protected,
                'video_file_exists' => $lesson->videoFileExists(),
                'video_metadata' => $lesson->video_metadata,
                'video_status_message' => $lesson->getVideoStatusMessage(),
            ];

            return $this->successResponse($videoInfo, 'تم جلب معلومات الفيديو بنجاح');

        } catch (\Exception $e) {
            Log::error('Get video info error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }
}