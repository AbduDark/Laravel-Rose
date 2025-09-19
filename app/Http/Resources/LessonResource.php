<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = Auth::user();
        $canAccess = $this->canUserAccess($user);

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->when($canAccess, $this->content),
            'duration_minutes' => $this->duration_minutes,
            'order' => $this->order,
            'is_free' => $this->is_free,
            'target_gender' => $this->target_gender,
            'can_access' => $canAccess,
            
            // معلومات الفيديو
            'has_video' => $this->hasVideo(),
            'video_status' => $this->when($user && $user->isAdmin(), $this->video_status),
            'video_duration_formatted' => $this->when($canAccess && $this->hasVideo(), $this->getFormattedDuration()),
            'video_size_formatted' => $this->when($canAccess && $this->hasVideo(), $this->getFormattedSize()),
            'video_stream_url' => $this->when($canAccess && $this->hasVideo(), $this->getVideoStreamUrl()),
            'is_video_protected' => $this->when($user && $user->isAdmin(), $this->is_video_protected),
            'video_status_message' => $this->when($this->video_status, $this->getVideoStatusMessage()),
            
            // معلومات الكورس (إذا تم تحميلها)
            'course' => $this->whenLoaded('course'),
            
            // التعليقات (إذا تم تحميلها)
            'comments' => $this->whenLoaded('comments'),
            
            // timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * تحديد ما إذا كان المستخدم يمكنه الوصول للدرس
     */
    private function canUserAccess(?object $user): bool
    {
        if (!$user) {
            return false;
        }

        // المديرين يمكنهم الوصول لكل شيء
        if ($user->isAdmin()) {
            return true;
        }

        // الدروس المجانية متاحة للجميع
        if ($this->is_free) {
            return true;
        }

        // التحقق من الاشتراك في الكورس
        return $this->course->subscriptions()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->exists();
    }
}
