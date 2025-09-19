<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class VideoController extends Controller
{
    use ApiResponseTrait;

    /**
     * رفع فيديو جديد للدرس
     */
    public function upload(Request $request, $lessonId)
    {
        try {
            // التحقق من صلاحيات المدير
            if (!$request->user() || !$request->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك برفع الفيديوهات', 403);
            }

            $request->validate([
                'video' => 'required|file|mimes:mp4,mov,avi,wmv,webm|max:2048000', // 2GB max
            ]);

            $lesson = Lesson::find($lessonId);
            if (!$lesson) {
                return $this->errorResponse('الدرس غير موجود', 404);
            }

            // حذف الفيديو السابق إذا وجد
            if ($lesson->video_path && Storage::exists($lesson->video_path)) {
                Storage::delete($lesson->video_path);
            }

            // رفع الفيديو
            $file = $request->file('video');
            $filename = 'lesson_' . $lesson->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $videoPath = $file->storeAs('videos', $filename, 'local');

            // الحصول على معلومات الفيديو
            $fullPath = storage_path('app/' . $videoPath);
            $videoSize = filesize($fullPath);
            $videoDuration = $this->getVideoDuration($fullPath);

            // تحديث الدرس
            $lesson->update([
                'video_path' => $videoPath,
                'video_size' => $videoSize,
                'video_duration' => $videoDuration,
                'video_status' => 'ready'
            ]);

            return $this->successResponse([
                'lesson_id' => $lesson->id,
                'video_path' => $videoPath,
                'video_size' => $videoSize,
                'video_duration' => $videoDuration,
                'stream_url' => route('api.video.stream', ['lesson' => $lesson->id]),
                'message' => 'تم رفع الفيديو بنجاح'
            ], 'تم رفع الفيديو بنجاح');

        } catch (\Exception $e) {
            Log::error('Video upload error: ' . $e->getMessage());
            return $this->errorResponse('خطأ في رفع الفيديو: ' . $e->getMessage(), 500);
        }
    }

    /**
     * بث الفيديو
     */
    public function stream(Request $request, $lessonId)
    {
        try {
            $lesson = Lesson::find($lessonId);
            if (!$lesson || !$lesson->video_path) {
                return $this->errorResponse('الفيديو غير موجود', 404);
            }

            // التحقق من صلاحية الوصول
            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً', 401);
            }

            // التحقق من الصلاحيات
            if (!$this->canAccessVideo($user, $lesson)) {
                return $this->errorResponse('ليس لديك صلاحية لمشاهدة هذا الفيديو', 403);
            }

            $videoPath = storage_path('app/' . $lesson->video_path);
            
            if (!file_exists($videoPath)) {
                Log::error('Video file not found: ' . $videoPath);
                return $this->errorResponse('ملف الفيديو غير موجود', 404);
            }

            $fileSize = filesize($videoPath);
            $start = 0;
            $end = $fileSize - 1;
            $partialContent = false;

            // دعم Range Requests
            if ($request->hasHeader('Range')) {
                $range = $request->header('Range');
                if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                    $start = intval($matches[1]);
                    if (!empty($matches[2])) {
                        $end = min(intval($matches[2]), $fileSize - 1);
                    }
                    $partialContent = true;
                }
            }

            $length = $end - $start + 1;
            $mimeType = mime_content_type($videoPath) ?: 'video/mp4';

            // Headers
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => $length,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];

            if ($partialContent) {
                $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            }

            $statusCode = $partialContent ? 206 : 200;

            return response()->stream(function () use ($videoPath, $start, $end) {
                $stream = fopen($videoPath, 'rb');
                fseek($stream, $start);
                $bytesRemaining = $end - $start + 1;
                $chunkSize = 8192;

                while ($bytesRemaining > 0 && !feof($stream)) {
                    $bytesToRead = min($chunkSize, $bytesRemaining);
                    $chunk = fread($stream, $bytesToRead);
                    
                    if ($chunk === false) {
                        break;
                    }

                    echo $chunk;
                    $bytesRemaining -= strlen($chunk);
                    
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
                
                fclose($stream);
            }, $statusCode, $headers);

        } catch (\Exception $e) {
            Log::error('Video stream error: ' . $e->getMessage());
            return $this->errorResponse('خطأ في بث الفيديو', 500);
        }
    }

    /**
     * الحصول على معلومات الفيديو
     */
    public function info(Request $request, $lessonId)
    {
        try {
            $lesson = Lesson::find($lessonId);
            if (!$lesson) {
                return $this->errorResponse('الدرس غير موجود', 404);
            }

            $user = $request->user();
            if (!$user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً', 401);
            }

            if (!$this->canAccessVideo($user, $lesson)) {
                return $this->errorResponse('ليس لديك صلاحية لمشاهدة هذا الفيديو', 403);
            }

            $hasVideo = !empty($lesson->video_path) && Storage::exists($lesson->video_path);

            return $this->successResponse([
                'lesson_id' => $lesson->id,
                'lesson_title' => $lesson->title,
                'has_video' => $hasVideo,
                'video_duration' => $lesson->video_duration,
                'video_size' => $lesson->video_size,
                'formatted_duration' => $this->formatDuration($lesson->video_duration),
                'formatted_size' => $this->formatSize($lesson->video_size),
                'stream_url' => $hasVideo ? route('api.video.stream', ['lesson' => $lesson->id]) : null,
                'can_access' => $this->canAccessVideo($user, $lesson)
            ], 'تم جلب معلومات الفيديو بنجاح');

        } catch (\Exception $e) {
            Log::error('Video info error: ' . $e->getMessage());
            return $this->errorResponse('خطأ في جلب معلومات الفيديو', 500);
        }
    }

    /**
     * حذف الفيديو
     */
    public function delete(Request $request, $lessonId)
    {
        try {
            if (!$request->user() || !$request->user()->isAdmin()) {
                return $this->errorResponse('غير مصرح لك بحذف الفيديوهات', 403);
            }

            $lesson = Lesson::find($lessonId);
            if (!$lesson) {
                return $this->errorResponse('الدرس غير موجود', 404);
            }

            if ($lesson->video_path && Storage::exists($lesson->video_path)) {
                Storage::delete($lesson->video_path);
            }

            $lesson->update([
                'video_path' => null,
                'video_size' => null,
                'video_duration' => null,
                'video_status' => null
            ]);

            return $this->successResponse(null, 'تم حذف الفيديو بنجاح');

        } catch (\Exception $e) {
            Log::error('Video delete error: ' . $e->getMessage());
            return $this->errorResponse('خطأ في حذف الفيديو', 500);
        }
    }

    /**
     * التحقق من صلاحية الوصول للفيديو
     */
    private function canAccessVideo($user, $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($lesson->is_free) {
            return true;
        }

        return $user->isSubscribedTo($lesson->course_id);
    }

    /**
     * الحصول على مدة الفيديو
     */
    private function getVideoDuration($videoPath)
    {
        try {
            if (function_exists('exec')) {
                $command = "ffprobe -v quiet -show_entries format=duration -of csv=\"p=0\" " . escapeshellarg($videoPath);
                $output = null;
                $returnVar = null;
                exec($command, $output, $returnVar);
                if ($returnVar === 0 && !empty($output[0])) {
                    return (int) round(floatval($output[0]));
                }
            }

            // استخدام getID3 كبديل
            if (class_exists('getID3')) {
                $getID3 = new \getID3();
                $fileInfo = $getID3->analyze($videoPath);
                if (isset($fileInfo['playtime_seconds'])) {
                    return (int) round($fileInfo['playtime_seconds']);
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error getting video duration: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تنسيق المدة
     */
    private function formatDuration($seconds)
    {
        if (!$seconds) return null;

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * تنسيق الحجم
     */
    private function formatSize($bytes)
    {
        if (!$bytes) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
