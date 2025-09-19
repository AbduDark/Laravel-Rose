<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage as StorageBase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'content',
        'order',
        'duration_minutes',
        'is_free',
        'target_gender',
        'video_path',
        'video_status', // processing, ready, failed
        'video_duration', // مدة الفيديو بالثواني
        'video_size', // حجم الفيديو بالبايت
        'video_token', // رمز الحماية للفيديو
        'video_token_expires_at', // انتهاء صلاحية رمز الفيديو
        'is_video_protected', // هل الفيديو محمي
        'video_metadata', // معلومات إضافية عن الفيديو
    ];

    protected $appends = ['can_access', 'has_video'];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_video_protected' => 'boolean',
            'video_duration' => 'integer',
            'video_size' => 'integer',
            'video_token_expires_at' => 'datetime',
            'video_metadata' => 'array',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Check if lesson has video
     */
    public function hasVideo(): bool
    {
        return !empty($this->video_path) && $this->video_status === 'ready';
    }

    /**
     * Generate video token for protected videos
     */
    public function generateVideoToken(int $expiresInMinutes = 60): string
    {
        $token = Str::random(64);

        $this->update([
            'video_token' => $token,
            'video_token_expires_at' => now()->addMinutes($expiresInMinutes)
        ]);

        return $token;
    }

    /**
     * Validate video token
     */
    public function isValidVideoToken(string $token): bool
    {
        if (!$this->is_video_protected) {
            return true;
        }

        return $this->video_token === $token &&
               $this->video_token_expires_at &&
               $this->video_token_expires_at->isFuture();
    }

    /**
     * Get video stream URL
     */
    public function getVideoStreamUrl(): ?string
    {
        if (!$this->hasVideo()) {
            return null;
        }

        if (!$this->is_video_protected) {
            return url("/api/lessons/{$this->id}/stream");
        }

        // إنشاء رمز حماية جديد صالح لمدة ساعة
        $token = $this->generateVideoToken(60);

        return url("/api/lessons/{$this->id}/stream?token={$token}");
    }

    /**
     * Get video status message
     */
    public function getVideoStatusMessage(): string
    {
        return match($this->video_status) {
            'processing' => 'جاري معالجة الفيديو...',
            'ready' => 'الفيديو جاهز للمشاهدة',
            'failed' => 'فشل في معالجة الفيديو',
            default => 'لم يتم رفع الفيديو'
        };
    }

    /**
     * الحصول على رابط بث الفيديو المحمي
     */
    public function getProtectedVideoStreamUrl(): ?string
    {
        if (!$this->hasVideo()) {
            return null;
        }

        // إنشاء رمز حماية جديد صالح لمدة ساعة
        $token = $this->generateVideoToken(60);

        return route('api.lessons.stream', [
            'lesson' => $this->id,
            'token' => $token
        ]);
    }

    /**
     * التحقق من حالة معالجة الفيديو
     */
    public function isVideoProcessing(): bool
    {
        return $this->video_status === 'processing';
    }

    /**
     * التحقق من فشل معالجة الفيديو
     */
    public function isVideoFailed(): bool
    {
        return $this->video_status === 'failed';
    }

    /**
     * تنسيق مدة الفيديو
     */
    public function getFormattedDuration(): ?string
    {
        if (!$this->video_duration) {
            return null;
        }

        $hours = floor($this->video_duration / 3600);
        $minutes = floor(($this->video_duration % 3600) / 60);
        $seconds = $this->video_duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * تنسيق حجم الفيديو
     */
    public function getFormattedSize(): ?string
    {
        if (!$this->video_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->video_size;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * الحصول على المسار الكامل للفيديو
     */
    public function getVideoPath(): ?string
    {
        if (!$this->video_path) {
            return null;
        }

        return storage_path('app/' . $this->video_path);
    }

    /**
     * التحقق من وجود ملف الفيديو فعلياً
     */
    public function videoFileExists(): bool
    {
        $path = $this->getVideoPath();
        return $path && file_exists($path);
    }

    /**
     * حذف ملف الفيديو من النظام
     */
    public function deleteVideoFile(): bool
    {
        if (!$this->video_path) {
            return true;
        }

        $deleted = true;

        // حذف الملف الأساسي
        if (Storage::exists($this->video_path)) {
            $deleted = Storage::delete($this->video_path);
        }

        // حذف مجلد الفيديو المحمي إذا وجد
        $protectedDir = storage_path("app/private_videos/lesson_{$this->id}");
        if (is_dir($protectedDir)) {
            $this->deleteDirectory($protectedDir);
        }

        return $deleted;
    }

    /**
     * حذف مجلد بالكامل
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        return rmdir($dir);
    }

    /**
     * Get can_access attribute
     */
    public function getCanAccessAttribute(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($this->is_free || $user->isAdmin()) {
            return true;
        }

        return $user->isSubscribedTo($this->course_id);
    }

    /**
     * Get has_video attribute
     */
    public function getHasVideoAttribute(): bool
    {
        return $this->hasVideo();
    }

    /**
     * تحديث معلومات الفيديو
     */
    public function updateVideoMetadata(array $metadata): void
    {
        $currentMetadata = $this->video_metadata ?? [];
        $newMetadata = array_merge($currentMetadata, $metadata);

        $this->update(['video_metadata' => $newMetadata]);
    }
}
