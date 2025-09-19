<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'vodafone_number' => $this->vodafone_number,
            'parent_phone' => $this->parent_phone,
            'student_info' => $this->student_info,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_approved' => $this->is_approved,
            'subscribed_at' => $this->subscribed_at,
            'expires_at' => $this->expires_at,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
            'admin_notes' => $this->admin_notes,
            'days_remaining' => $this->getDaysRemaining(),
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            
            // العلاقات
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'course' => $this->whenLoaded('course', function () {
                return new CourseResource($this->course);
            }),
            'approved_by_user' => $this->whenLoaded('approvedBy', function () {
                return new UserResource($this->approvedBy);
            }),
            'rejected_by_user' => $this->whenLoaded('rejectedBy', function () {
                return new UserResource($this->rejectedBy);
            }),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
