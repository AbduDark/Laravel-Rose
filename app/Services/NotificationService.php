<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Course;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * إرسال إشعار لمستخدم واحد
     */
    public static function sendToUser($userId, $title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        try {
            // التحقق من صحة البيانات
            if (empty($userId) || empty($title) || empty($message)) {
                Log::error('NotificationService::sendToUser - Invalid parameters');
                return false;
            }

            // التحقق من وجود المستخدم
            if (!User::find($userId)) {
                Log::error("NotificationService::sendToUser - User not found: {$userId}");
                return false;
            }

            return Notification::create([
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'user_id' => $userId,
                'course_id' => $courseId,
                'sender_id' => $senderId,
                'data' => is_array($data) ? json_encode($data) : $data
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationService::sendToUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إرسال إشعار لعدة مستخدمين
     */
    public static function sendToUsers($userIds, $title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        $notifications = [];

        foreach ($userIds as $userId) {
            $notifications[] = self::sendToUser($userId, $title, $message, $type, $courseId, $senderId, $data);
        }

        return $notifications;
    }

    /**
     * إرسال إشعار لجميع الطلبة
     */
    public static function sendToAllStudents($title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        $studentIds = User::where('role', 'student')->pluck('id')->toArray();
        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إرسال إشعار لطلبة حسب الجنس
     */
    public static function sendToStudentsByGender($gender, $title, $message, $type = 'general', $courseId = null, $senderId = null, $data = null)
    {
        $studentIds = User::where('role', 'student')
                         ->where('gender', $gender)
                         ->pluck('id')->toArray();
        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إرسال إشعار لطلبة كورس معين حسب الجنس
     */
    public static function sendToCourseStudentsByGender($courseId, $gender, $title, $message, $type = 'course', $senderId = null, $data = null)
    {
        $studentIds = User::whereHas('subscriptions', function ($query) use ($courseId) {
            $query->where('course_id', $courseId)
                  ->where('is_active', true)
                  ->where('status', 'approved');
        })->where('gender', $gender)->pluck('id')->toArray();

        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إرسال إشعار لطلبة كورس معين
     */
    public static function sendToCourseStudents($courseId, $title, $message, $type = 'course', $senderId = null, $data = null)
    {
        $studentIds = User::whereHas('subscriptions', function ($query) use ($courseId) {
            $query->where('course_id', $courseId)
                  ->where('is_active', true)
                  ->where('status', 'approved');
        })->pluck('id')->toArray();

        return self::sendToUsers($studentIds, $title, $message, $type, $courseId, $senderId, $data);
    }

    /**
     * إشعار عند الموافقة على الاشتراك
     */
    public static function subscriptionApproved($userId, $courseId)
    {
        try {
            $course = Course::find($courseId);
            
            if (!$course) {
                Log::error("NotificationService::subscriptionApproved - Course not found: {$courseId}");
                return false;
            }

            return self::sendToUser(
                $userId,
                'تم قبول اشتراكك',
                "تم قبول اشتراكك في كورس: {$course->title}. يمكنك الآن الوصول إلى جميع دروس الكورس.",
                'subscription',
                $courseId,
                null,
                ['action' => 'subscription_approved', 'course_id' => $courseId]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::subscriptionApproved error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار عند رفض الاشتراك
     */
    public static function subscriptionRejected($userId, $courseId, $reason = null)
    {
        try {
            $course = Course::find($courseId);
            
            if (!$course) {
                Log::error("NotificationService::subscriptionRejected - Course not found: {$courseId}");
                return false;
            }

            $message = "تم رفض اشتراكك في كورس: {$course->title}.";

            if ($reason) {
                $message .= " السبب: {$reason}";
            }

            return self::sendToUser(
                $userId,
                'تم رفض اشتراكك',
                $message,
                'subscription',
                $courseId,
                null,
                ['action' => 'subscription_rejected', 'course_id' => $courseId, 'reason' => $reason]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::subscriptionRejected error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار عند انتهاء صلاحية الاشتراك
     */
    public static function subscriptionExpired($userId, $courseId, $reason = null)
    {
        try {
            $course = Course::find($courseId);
            
            if (!$course) {
                Log::error("NotificationService::subscriptionExpired - Course not found: {$courseId}");
                return false;
            }

            return self::sendToUser(
                $userId,
                'انتهت صلاحية اشتراكك',
                "انتهت صلاحية اشتراكك في كورس: {$course->title}. يرجى التجديد.",
                'subscription',
                $courseId,
                null,
                ['action' => 'subscription_expired', 'course_id' => $courseId, 'reason' => $reason]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::subscriptionExpired error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار عند إضافة درس جديد
     */
    public static function newLessonAdded($courseId, $lessonTitle)
    {
        try {
            $course = Course::find($courseId);
            
            if (!$course) {
                Log::error("NotificationService::newLessonAdded - Course not found: {$courseId}");
                return false;
            }

            return self::sendToCourseStudents(
                $courseId,
                'درس جديد متاح',
                "تم إضافة درس جديد '{$lessonTitle}' إلى كورس: {$course->title}",
                'course',
                null,
                ['action' => 'new_lesson', 'course_id' => $courseId, 'lesson_title' => $lessonTitle]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::newLessonAdded error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار عند اقتراب انتهاء الاشتراك (تذكير)
     */
    public static function subscriptionExpiringReminder($userId, $courseId, $daysRemaining)
    {
        try {
            $course = Course::find($courseId);
            
            if (!$course) {
                Log::error("NotificationService::subscriptionExpiringReminder - Course not found: {$courseId}");
                return false;
            }

            return self::sendToUser(
                $userId,
                'تذكير: اشتراكك على وشك الانتهاء',
                "سينتهي اشتراكك في كورس: {$course->title} خلال {$daysRemaining} أيام. يرجى تجديد الاشتراك.",
                'subscription',
                $courseId,
                null,
                ['action' => 'subscription_reminder', 'course_id' => $courseId, 'days_remaining' => $daysRemaining]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::subscriptionExpiringReminder error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار للمديرين
     */
    public function notifyAdmins($title, $message, $type = 'info')
    {
        try {
            // البحث عن جميع المديرين
            $admins = User::where('role', 'admin')->orWhere('is_admin', true)->get();

            if ($admins->isEmpty()) {
                return false; // لا يوجد مدراء لإرسال الإشعار لهم
            }

            foreach ($admins as $admin) {
                self::sendToUser($admin->id, $title, $message, $type);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('NotificationService::notifyAdmins error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار عند تفعيل اشتراك جديد للمديرين
     */
    public function newSubscription($admin, $subscription)
    {
        try {
            return self::sendToUser(
                $admin->id,
                'طلب اشتراك جديد',
                "طلب اشتراك جديد من الطالب: {$subscription->user->name} في كورس: {$subscription->course->title}",
                'new_subscription',
                $subscription->course_id,
                $subscription->user_id,
                [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'course_id' => $subscription->course_id,
                    'action' => 'new_subscription_request'
                ]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::newSubscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * إشعار عند تجديد الاشتراك للمديرين
     */
    public function newSubscriptionRenewal($admin, $subscription)
    {
        try {
            return self::sendToUser(
                $admin->id,
                'طلب تجديد اشتراك',
                "طلب تجديد اشتراك من الطالب: {$subscription->user->name} في كورس: {$subscription->course->title}",
                'subscription_renewal',
                $subscription->course_id,
                $subscription->user_id,
                [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'course_id' => $subscription->course_id,
                    'action' => 'subscription_renewal_request'
                ]
            );
        } catch (\Exception $e) {
            Log::error('NotificationService::newSubscriptionRenewal error: ' . $e->getMessage());
            return false;
        }
    }
}
