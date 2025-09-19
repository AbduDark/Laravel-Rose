<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\{
    AuthController,
    CourseController,
    LessonController,
    SubscriptionController,
    FavoriteController,
    CommentController,
    RatingController,
    PaymentController,
    UserController,
    AdminController,
    NotificationController,
    LessonVideoController
};
use App\Http\Middleware\AdminMiddleware;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return response()->json([
        'message'   => 'Rose Academy API is running',
        'version'   => '1.0.0',
        'timestamp' => now(),
        'status'    => 'active'
    ]);
});
Route::get('/health', fn() => response()->json(['status' => 'OK', 'timestamp' => now()]));

// Debug route - remove in production
Route::middleware(['auth:sanctum'])->get('/debug/user', function () {
    $user = auth()->user();
    return response()->json([
        'user' => $user,
        'is_admin_column' => $user->is_admin ?? false,
        'role_column' => $user->role ?? null,
        'isAdmin_method' => $user->isAdmin(),
        'hasAdminRole_method' => $user->hasAdminRole(),
    ]);
});

// Debug route for video issues - remove in production

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login'])->name('login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::get('verify-email',     [AuthController::class, 'verifyEmail']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    Route::post('force-logout',    [AuthController::class, 'forceLogout']);
    Route::post('resend-pin',      [AuthController::class, 'resendPin']);
    Route::get('avatars/{filename}', [UserController::class, 'getAvatar']);
});

Route::get('courses',                  [CourseController::class, 'index']);
Route::get('courses/{id}',             [CourseController::class, 'show']);
Route::get('courses/{id}/ratings',     [RatingController::class, 'index']);

// Pagination routes for all models
Route::get('subscriptions',            [SubscriptionController::class, 'index']);
Route::get('lessons',                  [LessonController::class, 'publicIndex']);
Route::get('users',                    [UserController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::get('profile',   [AuthController::class, 'profile']);
        Route::put('update',   [AuthController::class, 'update']);
        Route::put('password',         [AuthController::class, 'changePassword']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::post('refresh',  [AuthController::class, 'refresh']);
        Route::post('logout',   [AuthController::class, 'logout']);
    });

    // Subscriptions
    Route::post('subscribe',                           [SubscriptionController::class, 'subscribe']);
    Route::get('my-subscriptions',                     [SubscriptionController::class, 'mySubscriptions']);
    Route::post('subscriptions/{id}/cancel',           [SubscriptionController::class, 'cancelSubscription']);
    Route::post('subscriptions/renew',                 [SubscriptionController::class, 'renewSubscription']);
    Route::get('expired-subscriptions',                [SubscriptionController::class, 'getExpiredSubscriptions']);
    Route::get('subscriptions/status/{courseId}',      [SubscriptionController::class, 'getSubscriptionStatus']);

    // Notifications
    Route::get('notifications',                        [NotificationController::class, 'index']);
    Route::get('notifications/unread-count',           [NotificationController::class, 'unreadCount']);
    Route::get('notifications/{id}',                   [NotificationController::class, 'show']);
    Route::put('notifications/{id}/read',              [NotificationController::class, 'markAsRead']);
    Route::put('notifications/mark-all-read',          [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{id}',                [NotificationController::class, 'destroy']);

    // Comments
    Route::post('comments',                            [CommentController::class, 'store']);
    Route::get('my-comments',                          [CommentController::class, 'getUserComments']);
    Route::delete('comments/{id}',                     [CommentController::class, 'destroy']);
    Route::get('lessons/{lessonId}/comments',          [CommentController::class, 'getLessonComments']);

    // Favorites
    Route::post('favorite/{course_id}',                [FavoriteController::class, 'add']);
    Route::delete('favorite/{course_id}',              [FavoriteController::class, 'remove']);

    // Lessons
    Route::get('courses/{id}/lessons',                 [LessonController::class, 'index']);
    Route::get('lessons/{id}',                         [LessonController::class, 'show']);
    Route::get('/lessons/{lesson}/stream',              [LessonVideoController::class, 'stream']);
    Route::get('/lessons/{lesson}/key', [LessonVideoController::class, 'getKey'])->name('video.key');

    // Ratings
    Route::post('ratings',                             [RatingController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', AdminMiddleware::class])
    ->prefix('admin')
    ->group(function () {

    // Courses & Lessons
   Route::post('/courses', [CourseController::class, 'store'])->middleware(['auth:sanctum', AdminMiddleware::class]);
   Route::put('/courses/{id}', [CourseController::class, 'update'])->middleware(['auth:sanctum', AdminMiddleware::class]);
   Route::patch('/courses/{id}', [CourseController::class, 'update'])->middleware(['auth:sanctum', AdminMiddleware::class]);
   Route::delete('/courses/{id}', [CourseController::class, 'destroy'])->middleware(['auth:sanctum', AdminMiddleware::class]);
    Route::get('lessons', [LessonController::class, 'adminIndex']);
    Route::apiResource('lessons', LessonController::class)->except(['index', 'show']);

    // Users
    Route::controller(AdminController::class)->group(function () {
        Route::get('users',                 'getUsers');
        Route::get('users/{id}',             'getUserDetails');
        Route::put('users/{id}',             'updateUser');
        Route::delete('users/{id}',          'deleteUser');
        Route::get('dashboard/stats',        'getDashboardStats');

        // Subscriptions
        Route::get('subscriptions',          'getSubscriptions');
        Route::get('subscriptions/pending',  'getPendingSubscriptions');
        Route::post('subscriptions/{id}/approve', 'approveSubscription');
        Route::post('subscriptions/{id}/reject',  'rejectSubscription');
    });

    // Comments
    Route::prefix('comments')->group(function () {
        Route::get('pending',                 [CommentController::class, 'getPendingComments']);
        Route::post('{id}/approve',           [CommentController::class, 'approveComment']);
    });

    // Admin Subscriptions
    Route::get('subscriptions',               [SubscriptionController::class, 'adminIndex']);
    Route::put('subscriptions/{id}/approve',  [SubscriptionController::class, 'approve']);
    Route::put('subscriptions/{id}/reject',   [SubscriptionController::class, 'reject']);

    // Notifications
    Route::post('notifications/send',         [NotificationController::class, 'sendNotification']);
    Route::get('notifications/statistics',    [NotificationController::class, 'statistics']);
});
/*
|--------------------------------------------------------------------------
| Video Management Routes - Direct URL System
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\VideoController;

// Public video info
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('video/{lesson}/info', [VideoController::class, 'info'])->name('api.video.info');
});

// Admin video management
Route::middleware(['auth:sanctum', AdminMiddleware::class])
    ->prefix('admin')
    ->group(function () {
        Route::post('video/{lesson}/upload', [VideoController::class, 'upload'])->name('admin.video.upload');
        Route::delete('video/{lesson}/delete', [VideoController::class, 'delete'])->name('admin.video.delete');
    });