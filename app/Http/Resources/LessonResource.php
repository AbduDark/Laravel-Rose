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
            'content' => $this->content,
            'order' => $this->order,
            'duration_minutes' => $this->duration_minutes,
            'is_free' => $this->is_free,
            'target_gender' => $this->target_gender,
            'can_access' => $canAccess,
            'has_video' => $this->has_video,
            'video_stream_url' => $this->can_access && $this->has_video
                ? route('api.video.stream', ['lesson' => $this->id])
                : null,
            'video_duration_formatted' => $this->has_video ? $this->getFormattedDuration() : null,
            'video_size_formatted' => $this->has_video ? $this->getFormattedSize() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'course' => $this->whenLoaded('course'),
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