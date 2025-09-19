<?php

namespace App\Http\Controllers\Api;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, Cache, Log, Validator, Auth};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\{Course, Subscription, User};
use App\Http\Resources\CourseResource;

use App\Services\{CourseImageGenerator, ImageOptimizer};
use Symfony\Component\HttpFoundation\Response;

class CourseController extends BaseController
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        try {
            $cacheKey = 'courses_' . md5(serialize($request->all()));

            $courses = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request) {
                $query = Course::where('is_active', true);
                    // ->with(['ratings'])
                    // ->withCount('lessons');

                // Search
                if ($request->has('search')) {
                    $query->where(function($q) use ($request) {
                        $q->where('title', 'like', "%{$request->search}%")
                          ->orWhere('description', 'like', "%{$request->search}%")
                          ->orWhere('instructor_name', 'like', "%{$request->search}%");
                    });
                }

                // Filters
                $query->when($request->level, fn($q, $level) => $q->where('level', $level))
                     ->when($request->language, fn($q, $lang) => $q->where('language', $lang))
                     ->when($request->grade, fn($q, $grade) => $q->where('grade', $grade))
                     ->when($request->min_price, fn($q, $min) => $q->where('price', '>=', $min))
                     ->when($request->max_price, fn($q, $max) => $q->where('price', '<=', $max));

                // Sorting
                $sortBy = in_array($request->sort_by, ['title', 'price', 'created_at', 'duration_hours'])
                    ? $request->sort_by
                    : 'created_at';

                $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

                return $query->orderBy($sortBy, $sortOrder)
                           ->paginate($request->per_page ?? 10);
            });

            return CourseResource::collection($courses)->additional([
                'message' => [
                    'ar' => 'تم جلب الكورسات بنجاح',
                    'en' => 'Courses retrieved successfully'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Courses index error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->serverErrorResponse();
        }
    }

    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $course = Course::with(['ratings.user'])
                          ->findOrFail($id);

            if (!$course->is_active) {
                return $this->errorResponse([
                    'ar' => 'الكورس غير متاح حالياً',
                    'en' => 'Course not available'
                ], 404);
            }

            $course->load(['lessons' => function($query) use ($user, $course) {
                $query->orderBy('order');

                if ($user) {
                    if ($user->isSubscribedTo($course->id)) {
                        $query->where(function($q) use ($user) {
                            $q->where('target_gender', 'both')
                              ->orWhere('target_gender', $user->gender);
                        });
                    } else {
                        $query->where('is_free', true)
                              ->where(function($q) use ($user) {
                                  $q->where('target_gender', 'both')
                                    ->orWhere('target_gender', $user->gender);
                              });
                    }
                } else {
                    $query->where('is_free', true)
                          ->where('target_gender', 'both');
                }
            }]);

            $course->average_rating = $course->averageRating();
            $course->total_ratings = $course->totalRatings();
            $course->is_subscribed = $user ? $user->isSubscribedTo($id) : false;
            $course->is_favorited = $user ? $user->hasFavorited($id) : false;

            $subscriptionInfo = null;

            if ($user && !$user->isAdmin()) {
                $subscription = $user->subscriptions()
                    ->where('course_id', $id)
                    ->where('status', 'approved')
                    ->first();

                if ($subscription) {
                    $subscriptionInfo = [
                        'is_subscribed' => true,
                        'is_active' => $subscription->is_active,
                        'is_expired' => $subscription->isExpired(),
                        'expires_at' => $subscription->expires_at,
                        'days_remaining' => $subscription->getDaysRemaining(),
                        'hours_remaining' => $subscription->getHoursRemaining(),
                        'is_expiring_soon' => $subscription->isExpiringSoon(),
                        'subscription_id' => $subscription->id
                    ];
                } else {
                    $subscriptionInfo = [
                        'is_subscribed' => false,
                        'message' => [
                            'ar' => 'يجب الاشتراك في هذا الكورس للوصول إلى محتواه',
                            'en' => 'You must subscribe to this course to access its content'
                        ]
                    ];
                }
            }


            return $this->successResponse(
                new CourseResource($course),
                [
                    'ar' => 'تم جلب الكورس بنجاح',
                    'en' => 'Course retrieved successfully'
                ]
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course show error', [
                'course_id' => $id,
                'user_id' => $user?->id ?? 'guest',
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse();
        }
    }

   public function store(Request $request)
{
    // التحقق من أن المستخدم مسجل دخول ومدير
    if (!auth()->check() || !auth()->user()->isAdmin()) {
        return response()->json([
            'success' => false,
            'status_code' => 403,
            'message' => [
                'ar' => 'ممنوع - ليس لديك صلاحية للوصول',
                'en' => 'Forbidden - You do not have permission'
            ]
        ], 403);
    }

    try {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:courses',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'level' => 'nullable|in:beginner,intermediate,advanced',
            'duration_hours' => 'nullable|integer|min:0',
            'requirements' => 'nullable|string',
            'instructor_name' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'grade' => 'required|in:الاول,الثاني,الثالث',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $request->except('image');
        $data['language'] = $data['language'] ?? 'ar';
        $data['instructor_name'] = $data['instructor_name'] ?? 'أ.روز';

        // رفع الصورة
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/courses'), $filename);
            $data['image'] = 'uploads/courses/' . $filename;
        }

        $course = Course::create($data);
        Cache::forget('courses_' . md5(''));

        return $this->successResponse(
            new CourseResource($course),
            [
                'ar' => 'تم إنشاء الكورس بنجاح',
                'en' => 'Course created successfully'
            ],
            201
        );

    } catch (ValidationException $e) {
        return $this->validationErrorResponse($e);
    } catch (\Exception $e) {
        Log::error('Error creating course: ' . $e->getMessage());
        return $this->errorResponse(
            ['ar' => 'حدث خطأ أثناء إنشاء الدورة', 'en' => 'An error occurred while creating the course'],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    } catch (\Throwable $e) {
        Log::error('Error creating course: ' . $e->getMessage());

        return $this->errorResponse(
            ['ar' => 'حدث خطأ أثناء إنشاء الدورة', 'en' => 'An error occurred while creating the course'],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}



    public function update(Request $request, $id)
    {
    
    if (!auth()->check() || !auth()->user()->isAdmin()) {
        return response()->json([
            'success' => false,
            'status_code' => 403,
            'message' => [
                'ar' => 'ممنوع - ليس لديك صلاحية للوصول',
                'en' => 'Forbidden - You do not have permission'
            ]
        ], 403);
    }

    try {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255|unique:courses,title,' . $id,
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'level' => 'nullable|in:beginner,intermediate,advanced',
            'duration_hours' => 'nullable|integer|min:0',
            'requirements' => 'nullable|string',
            'instructor_name' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'grade' => 'sometimes|required|in:الاول,الثاني,الثالث',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $validated;

        // ✅ لو فيه صورة جديدة في الريكويست
        if ($request->hasFile('image')) {
            // 🗑️ امسح الصورة القديمة من السيرفر لو موجودة
            if ($course->image && file_exists(public_path($course->image))) {
                @unlink(public_path($course->image));
            }

            // 📤 ارفع الصورة الجديدة
            $image = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/courses'), $filename);

            $data['image'] = 'uploads/courses/' . $filename;
        }

        // ✍️ حدّث الكورس بالبيانات
        $course->update($data);

        // 📌 جهّز الريسبونس مع لينك الصورة
        $courseFresh = $course->fresh();
        $courseFresh->image_url = $courseFresh->image 
            ? url($courseFresh->image) 
            : null;

        return response()->json([
            'success' => true,
            'message' => [
                'ar' => 'تم تحديث الكورس بنجاح',
                'en' => 'Course updated successfully'
            ],
            'data'    => $courseFresh
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => [
                'ar' => 'خطأ في التحقق من البيانات',
                'en' => 'Validation error'
            ],
            'errors'  => $e->errors()
        ], 422);
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => [
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ]
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => [
                'ar' => 'حدث خطأ غير متوقع',
                'en' => 'Unexpected error occurred'
            ],
            'error'   => $e->getMessage()
        ], 500);
    }
}







    public function destroy(string $id)
    {
        // التحقق من أن المستخدم مسجل دخول ومدير
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'status_code' => 403,
                'message' => [
                    'ar' => 'ممنوع - ليس لديك صلاحية للوصول',
                    'en' => 'Forbidden - You do not have permission'
                ]
            ], 403);
        }

        try {
            $course = Course::findOrFail($id);

            if ($course->image && Storage::disk('public')->exists($course->image)) {
                Storage::disk('public')->delete($course->image);
            }

            $course->delete();
            Cache::forget('courses_' . md5(''));  // مسح كاش القائمة
            Cache::forget('course_' . $id);       // مسح كاش الكورس المحذوف

            return $this->successResponse(
                null,
                [
                    'ar' => 'تم حذف الكورس بنجاح',
                    'en' => 'Course deleted successfully'
                ]
            );

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse([
                'ar' => 'الكورس غير موجود',
                'en' => 'Course not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course deletion error', [
                'course_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse();
        }
    }
}