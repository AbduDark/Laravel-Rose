<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'role',
        'image',
        'active_session_id',
        'device_fingerprint',
        'last_login_at',
        'current_session',
        'profile_image',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    // ===================== Relationships ===================== //

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function sentNotifications()
    {
        return $this->hasMany(UserNotification::class, 'sender_id');
    }

    // ===================== Admin Helpers ===================== //

    public function isAdmin()
    {
        return (bool) $this->is_admin;
    }

    public function hasAdminRole()
    {
        return $this->role === 'admin';
    }

    public function isAdminAny()
    {
        return $this->isAdmin() || $this->hasAdminRole();
    }

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true)->orWhere('role', 'admin');
    }

    // ===================== Subscriptions ===================== //

    public function isSubscribedTo($courseId): bool
    {
        // Admins have access to all courses
        if ($this->isAdmin()) {
            return true;
        }

        return $this->subscriptions()
            ->where('course_id', $courseId)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

   

    // ===================== Favorites ===================== //

    public function hasFavorited($courseId)
    {
        return $this->favorites()
            ->where('course_id', $courseId)
            ->exists();
    }
    public function hasActiveSubscription($courseId): bool
{
    return $this->subscriptions()
        ->where('course_id', $courseId)
        ->where('status', 'approved')
        ->where('is_active', true)
        ->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->exists();
}

public function getActiveSubscription($courseId): ?Subscription
{
    return $this->subscriptions()
        ->where('course_id', $courseId)
        ->where('status', 'approved')
        ->where('is_active', true)
        ->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->first();
}

public function canAccessCourse($courseId): bool
{
    // Admins يقدروا يدخلوا على أي كورس
    if ($this->isAdminAny()) {
        return true;
    }

    return $this->hasActiveSubscription($courseId);
}

}
