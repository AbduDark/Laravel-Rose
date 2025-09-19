<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use App\Models\User;
/**
 * Class NotifyExpiredSubscriptions
 * @package App\Console\Commands
 *
 * This command checks for expired subscriptions and sends notifications to users.
 * Also sends weekly encouragement notifications to students.
 */
class NotifyExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expired {--weekly-encouragement : Send weekly encouragement to students}';
    protected $description = 'Send notifications to users whose subscriptions have expired and weekly encouragement messages';

    protected NotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new NotificationService();
    }

    public function handle()
    {
        if ($this->option('weekly-encouragement')) {
            $this->sendWeeklyEncouragement();
            return Command::SUCCESS;
        }

        $this->notifyExpiringSoonSubscriptions();
        $this->notifyExpiredSubscriptions();

        return Command::SUCCESS;
    }

    /**
     * إرسال إشعارات للاشتراكات التي ستنتهي قريباً
     */
    private function notifyExpiringSoonSubscriptions(): void
    {
        $this->info('التحقق من الاشتراكات التي ستنتهي قريباً...');

        // البحث عن الاشتراكات التي ستنتهي خلال 3 أيام
        $expiringSoonSubscriptions = Subscription::where('status', 'approved')
            ->where('is_active', true)
            ->whereDate('expires_at', '>', Carbon::now())
            ->whereDate('expires_at', '<=', Carbon::now()->addDays(3))
            ->with(['user', 'course'])
            ->get();

        $notifiedCount = 0;

        foreach ($expiringSoonSubscriptions as $subscription) {
            try {
                // التحقق من عدم إرسال تذكير مسبقاً في آخر يومين
                $alreadyNotified = Notification::where('user_id', $subscription->user_id)
                    ->where('course_id', $subscription->course_id)
                    ->where('type', 'subscription_expiring_soon')
                    ->where('created_at', '>', Carbon::now()->subDays(2))
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $daysRemaining = $subscription->getDaysRemaining();
                
                NotificationService::sendToUser(
                    $subscription->user_id,
                    'تذكير: اشتراكك سينتهي قريباً',
                    "سينتهي اشتراكك في كورس '{$subscription->course->title}' خلال {$daysRemaining} " . 
                    ($daysRemaining == 1 ? 'يوم' : 'أيام') . ". يرجى التجديد لمتابعة التعلم.",
                    'subscription_expiring_soon',
                    $subscription->course_id,
                    null,
                    [
                        'course_title' => $subscription->course->title,
                        'days_remaining' => $daysRemaining,
                        'expires_at' => $subscription->expires_at->format('Y-m-d'),
                        'subscription_id' => $subscription->id
                    ]
                );

                $notifiedCount++;
                $this->info("✅ تم إرسال تذكير للمستخدم: {$subscription->user->name} - كورس: {$subscription->course->title}");

            } catch (\Exception $e) {
                $this->error("❌ خطأ في إرسال التذكير للمستخدم {$subscription->user->name}: {$e->getMessage()}");
            }
        }

        $this->info("تم إرسال {$notifiedCount} تذكير للاشتراكات التي ستنتهي قريباً");
    }

    /**
     * إرسال إشعارات للاشتراكات المنتهية الصلاحية
     */
    private function notifyExpiredSubscriptions(): void
    {
        $this->info('التحقق من الاشتراكات المنتهية الصلاحية...');

        // البحث عن الاشتراكات المنتهية الصلاحية
        $expiredSubscriptions = Subscription::where('status', 'approved')
            ->where('is_active', true)
            ->whereDate('expires_at', '<', Carbon::now())
            ->with(['user', 'course'])
            ->get();

        $updatedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            try {
                // تحديث حالة الاشتراك إلى منتهي الصلاحية
                $subscription->update(['status' => 'expired', 'is_active' => false]);

                // التحقق من عدم إرسال إشعار انتهاء الصلاحية مسبقاً
                $alreadyNotified = Notification::where('user_id', $subscription->user_id)
                    ->where('course_id', $subscription->course_id)
                    ->where('type', 'subscription_expired')
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                NotificationService::sendToUser(
                    $subscription->user_id,
                    'انتهت صلاحية اشتراكك',
                    "انتهت صلاحية اشتراكك في كورس '{$subscription->course->title}'. " .
                    "للاستمرار في الوصول للدروس، يرجى تجديد الاشتراك من خلال التطبيق.",
                    'subscription_expired',
                    $subscription->course_id,
                    null,
                    [
                        'course_title' => $subscription->course->title,
                        'expired_at' => $subscription->expires_at->format('Y-m-d'),
                        'subscription_id' => $subscription->id,
                        'renewal_available' => true
                    ]
                );

                $updatedCount++;
                $this->info("✅ تم تحديث وإشعار المستخدم: {$subscription->user->name} - كورس: {$subscription->course->title}");

            } catch (\Exception $e) {
                $this->error("❌ خطأ في معالجة الاشتراك المنتهي للمستخدم {$subscription->user->name}: {$e->getMessage()}");
            }
        }

        $this->info("تم معالجة {$updatedCount} اشتراك منتهي الصلاحية");
    }

    /**
     * إرسال رسائل تشجيعية أسبوعية للطلاب
     */
    private function sendWeeklyEncouragement(): void
    {
        $this->info('إرسال رسائل التشجيع الأسبوعية للطلاب...');

        // البحث عن جميع الطلاب النشطين
        $activeStudents = User::where('role', 'student')
            ->whereHas('subscriptions', function ($query) {
                $query->where('status', 'approved')
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', Carbon::now());
                    });
            })
            ->with('subscriptions.course')
            ->get();

        // رسائل تشجيعية متنوعة
        $encouragementMessages = [
            'استمر في رحلتك التعليمية! كل خطوة تقربك من أهدافك. نتمنى لك التوفيق والنجاح! 🌟',
            'التعلم رحلة ممتعة، وأنت تسير في الطريق الصحيح. واصل العمل الجيد! 💪',
            'نحن فخورون بإصرارك على التعلم. استمر وستحقق المعجزات! ✨',
            'كل درس جديد يضيف لك مهارة قيمة. أنت على الطريق الصحيح للنجاح! 🎯',
            'التعليم استثمار في مستقبلك. نتمنى لك أسبوعاً مليئاً بالإنجازات! 🚀',
            'المثابرة مفتاح النجاح. أنت تقوم بعمل رائع، استمر هكذا! 🏆'
        ];

        $sentCount = 0;

        foreach ($activeStudents as $student) {
            try {
                // التحقق من عدم إرسال رسالة تشجيعية في آخر 6 أيام
                $recentEncouragement = Notification::where('user_id', $student->id)
                    ->where('type', 'weekly_encouragement')
                    ->where('created_at', '>', Carbon::now()->subDays(6))
                    ->exists();

                if ($recentEncouragement) {
                    continue;
                }

                // اختيار رسالة عشوائية
                $message = $encouragementMessages[array_rand($encouragementMessages)];
                
                // الحصول على الكورس النشط للطالب
                $activeCourse = $student->subscriptions
                    ->where('status', 'approved')
                    ->where('is_active', true)
                    ->first();

                NotificationService::sendToUser(
                    $student->id,
                    'رسالة تشجيعية من روز اكاديمي',
                    $message,
                    'weekly_encouragement',
                    $activeCourse ? $activeCourse->course_id : null,
                    null,
                    [
                        'week' => Carbon::now()->weekOfYear,
                        'year' => Carbon::now()->year,
                        'student_name' => $student->name,
                        'active_courses_count' => $student->subscriptions
                            ->where('status', 'approved')
                            ->where('is_active', true)
                            ->count()
                    ]
                );

                $sentCount++;
                $this->info("✅ تم إرسال رسالة تشجيعية للطالب: {$student->name}");

            } catch (\Exception $e) {
                $this->error("❌ خطأ في إرسال رسالة تشجيعية للطالب {$student->name}: {$e->getMessage()}");
            }
        }

        $this->info("تم إرسال {$sentCount} رسالة تشجيعية للطلاب");
    }
}