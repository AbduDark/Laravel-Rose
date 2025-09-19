<?php

// namespace App\Jobs;

// use App\Models\Lesson;
// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\SerializesModels;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\Cache;

// class ProcessLessonVideo implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     protected $lesson;

//     /**
//      * The number of times the job may be attempted.
//      *
//      * @var int
//      */
//     public $tries = 3;

//     /**
//      * The maximum number of seconds the job should run.
//      *
//      * @var int
//      */
//     public $timeout = 3600; // 30 minutes

//     /**
//      * Create a new job instance.
//      */
//     public function __construct(Lesson $lesson)
//     {
//         $this->lesson = $lesson;
//         $this->onQueue('video-processing');
//     }

//     /**
//      * Execute the job.
//      */
//     public function handle(): void
//     {
//         $lesson = null;

//         try {
//             Log::info("🎬 بدء معالجة الفيديو للدرس: {$this->lesson->id}");

//             // التحقق من وجود الدرس في قاعدة البيانات
//             $lesson = Lesson::where('id', $this->lesson->id)->first();

//             if (!$lesson) {
//                 Log::error("❌ الدرس غير موجود: {$this->lesson->id}");
//                 throw new \Exception("الدرس غير موجود: {$this->lesson->id}");
//             }

//             Log::info("✅ تم العثور على الدرس: {$lesson->id} - العنوان: {$lesson->title}");

//             // التحقق من وجود مسار الفيديو
//             if (empty($lesson->video_path)) {
//                 Log::error("❌ مسار الفيديو فارغ للدرس: {$lesson->id}");
//                 throw new \Exception("مسار الفيديو فارغ للدرس: {$lesson->id}");
//             }

//             Log::info("📁 مسار الفيديو: {$lesson->video_path}");

//             // التحقق من حالة الفيديو الحالية
//             if ($lesson->video_status === 'ready') {
//                 Log::info("⚠️ الفيديو جاهز بالفعل للدرس: {$lesson->id}");
//                 return;
//             }

//             // التحقق من أن الفيديو ليس قيد المعالجة في job آخر
//             if ($lesson->video_status === 'processing') {
//                 $processingTime = Cache::get("video_processing_started_{$lesson->id}");
//                 if ($processingTime && (time() - $processingTime) < 1800) { // 30 minutes
//                     Log::info("⚠️ الفيديو قيد المعالجة بالفعل للدرس: {$lesson->id}");
//                     return;
//                 }
//             }

//             $sourcePath = storage_path('app/' . $lesson->video_path);
//             $outputDir = storage_path("app/private_videos/lesson_{$lesson->id}");

//             Log::info("📍 المسار الكامل للفيديو: {$sourcePath}");
//             Log::info("📂 مجلد الإخراج: {$outputDir}");

//             // التحقق من صحة الملف
//             $this->validateVideoFile($sourcePath);

//             // إنشاء المجلدات المطلوبة
//             $this->createDirectories($outputDir);

//             // تحديث حالة المعالجة
//             $lesson->update(['video_status' => 'processing']);
//             Cache::put("video_processing_started_{$lesson->id}", time(), 3600);

//             // معالجة الفيديو بناءً على إعدادات الحماية
//             $finalVideoPath = $this->processVideo($lesson, $sourcePath, $outputDir);

//             // الحصول على معلومات الفيديو
//             $videoInfo = $this->getVideoInfo($finalVideoPath);

//             // تحديث بيانات الدرس
//             $relativePath = $this->getRelativePath($finalVideoPath);
            
//             $lesson->update([
//                 'video_status' => 'ready',
//                 'video_duration' => $videoInfo['duration'] ?? null,
//                 'video_size' => $videoInfo['size'] ?? null,
//                 'video_path' => $relativePath
//             ]);

//             // تحديث الـ metadata
//             $lesson->updateVideoMetadata([
//                 'processing_completed_at' => now()->toISOString(),
//                 'final_file_path' => $relativePath,
//                 'processing_time_seconds' => time() - Cache::get("video_processing_started_{$lesson->id}", time()),
//                 'file_validated' => true,
//                 'protection_applied' => $lesson->is_video_protected,
//             ]);

//             // حذف الملف المؤقت الأصلي
//             $this->cleanupOriginalFile($lesson, $sourcePath);

//             Log::info("✅ تمت معالجة الفيديو بنجاح للدرس: {$lesson->id}");

//         } catch (\Exception $e) {
//             Log::error("❌ خطأ في معالجة الفيديو للدرس: {$this->lesson->id}", [
//                 'error' => $e->getMessage(),
//                 'file' => $e->getFile(),
//                 'line' => $e->getLine(),
//                 'attempt' => $this->attempts(),
//                 'max_tries' => $this->tries
//             ]);

//             // تحديث حالة الدرس في قاعدة البيانات فقط في المحاولة الأخيرة
//             if ($lesson) {
//                 $processingTime = time() - Cache::get("video_processing_started_{$lesson->id}", time());
                
//                 if ($this->attempts() >= $this->tries) {
//                     // هذه المحاولة الأخيرة، تحديث الحالة إلى failed
//                     $lesson->update(['video_status' => 'failed']);
                    
//                     Log::error("💥 فشل نهائي في معالجة الفيديو للدرس: {$this->lesson->id} بعد {$this->tries} محاولات");
//                 } else {
//                     // ليست المحاولة الأخيرة، الاحتفاظ بحالة processing
//                     Log::warning("🔄 المحاولة {$this->attempts()} من {$this->tries} فشلت، سيتم إعادة المحاولة...");
//                 }
                
//                 // تحديث metadata بمعلومات الخطأ
//                 $lesson->updateVideoMetadata([
//                     'last_error_at' => now()->toISOString(),
//                     'last_error_message' => $e->getMessage(),
//                     'processing_attempts' => $this->attempts(),
//                     'processing_time_seconds' => $processingTime,
//                     'will_retry' => $this->attempts() < $this->tries,
//                 ]);
//             }

//             // تنظيف الملفات في حالة الفشل النهائي فقط
//             if ($this->attempts() >= $this->tries) {
//                 $this->cleanup();
//             }

//             throw $e;
//         }
//     }

//     /**
//      * Handle a job failure.
//      */
//     public function failed(\Throwable $exception): void
//     {
//         Log::error("💥 فشل نهائي في معالجة الفيديو للدرس: {$this->lesson->id}", [
//             'error' => $exception->getMessage(),
//             'file' => $exception->getFile(),
//             'line' => $exception->getLine(),
//             'trace' => $exception->getTraceAsString(),
//             'attempts' => $this->attempts(),
//             'max_tries' => $this->tries
//         ]);

//         // تحديث حالة الدرس إلى فاشل
//         try {
//             $lesson = Lesson::where('id', $this->lesson->id)->first();
//             if ($lesson) {
//                 $lesson->update(['video_status' => 'failed']);
                
//                 $lesson->updateVideoMetadata([
//                     'final_failure_at' => now()->toISOString(),
//                     'final_error_message' => $exception->getMessage(),
//                     'total_attempts' => $this->attempts(),
//                     'max_attempts_reached' => true,
//                 ]);
                
//                 Log::info("✅ تم تحديث حالة الدرس {$this->lesson->id} إلى 'failed'");
//             }
//         } catch (\Exception $e) {
//             Log::error("❌ لا يمكن تحديث حالة الدرس {$this->lesson->id}: " . $e->getMessage());
//         }

//         // تنظيف الملفات المؤقتة
//         $this->cleanup();
//     }

//     /**
//      * Calculate the number of seconds to wait before retrying the job.
//      */
//     public function retryAfter(): int
//     {
//         return 60; // انتظار دقيقة واحدة بين المحاولات
//     }

//     /**
//      * معالجة الفيديو بناءً على إعدادات الحماية
//      */
//     private function processVideo(Lesson $lesson, string $sourcePath, string $outputDir): string
//     {
//         $fileExtension = pathinfo($sourcePath, PATHINFO_EXTENSION);
//         $finalVideoPath = $outputDir . '/video.' . $fileExtension;

//         if ($lesson->is_video_protected) {
//             // نقل الفيديو إلى مجلد محمي
//             Log::info("🔒 معالجة فيديو محمي للدرس: {$lesson->id}");
            
//             if (!copy($sourcePath, $finalVideoPath)) {
//                 throw new \Exception("فشل في نقل الفيديو إلى المجلد المحمي");
//             }
            
//             // إنشاء ملف .htaccess للحماية الإضافية
//             $this->createProtectionFile($outputDir);
            
//         } else {
//             // نقل الفيديو إلى مجلد عادي
//             Log::info("🔓 معالجة فيديو غير محمي للدرس: {$lesson->id}");
            
//             $publicDir = storage_path("app/public/videos/lesson_{$lesson->id}");
//             $this->createDirectories($publicDir);
            
//             $finalVideoPath = $publicDir . '/video.' . $fileExtension;
            
//             if (!copy($sourcePath, $finalVideoPath)) {
//                 throw new \Exception("فشل في نقل الفيديو إلى المجلد العام");
//             }
//         }

//         Log::info("✅ تم نقل الفيديو بنجاح إلى: {$finalVideoPath}");
        
//         return $finalVideoPath;
//     }

//     /**
//      * إنشاء ملف الحماية .htaccess
//      */
//     private function createProtectionFile(string $outputDir): void
//     {
//         $htaccessContent = "
// # منع الوصول المباشر للملفات
// Order Deny,Allow
// Deny from all

// # منع عرض قائمة الملفات
// Options -Indexes

// # منع الوصول لأنواع معينة من الملفات
// <FilesMatch \"\.(mp4|avi|mov|wmv|webm)$\">
//     Order Deny,Allow
//     Deny from all
// </FilesMatch>

// # إعادة توجيه أي محاولة وصول
// RewriteEngine On
// RewriteCond %{REQUEST_FILENAME} !^$
// RewriteRule .* - [F,L]
// ";

//         file_put_contents($outputDir . '/.htaccess', $htaccessContent);
//         Log::info("🛡️ تم إنشاء ملف الحماية .htaccess");
//     }

//     /**
//      * الحصول على المسار النسبي للفيديو
//      */
//     private function getRelativePath(string $fullPath): string
//     {
//         $appPath = storage_path('app/');
//         return str_replace($appPath, '', $fullPath);
//     }

//     /**
//      * تنظيف الملف الأصلي
//      */
//     private function cleanupOriginalFile(Lesson $lesson, string $sourcePath): void
//     {
//         // التحقق من أن الملف المؤقت ليس هو نفس الملف النهائي
//         if (strpos($lesson->video_path, 'temp_videos') !== false) {
//             if (file_exists($sourcePath)) {
//                 if (unlink($sourcePath)) {
//                     Log::info("✅ تم حذف الملف المؤقت الأصلي: {$sourcePath}");
//                 } else {
//                     Log::warning("⚠️ فشل في حذف الملف المؤقت الأصلي: {$sourcePath}");
//                 }
//             }
//         }
//     }

//     /**
//      * التحقق من صحة ملف الفيديو
//      */
//     private function validateVideoFile(string $videoPath): void
//     {
//         Log::info("🔍 فحص ملف الفيديو: {$videoPath}");

//         // التحقق من وجود الملف
//         if (!file_exists($videoPath)) {
//             Log::error("❌ ملف الفيديو غير موجود: {$videoPath}");
            
//             // التحقق من وجود المجلد
//             $directory = dirname($videoPath);
//             if (!is_dir($directory)) {
//                 Log::error("❌ المجلد غير موجود: {$directory}");
//                 throw new \Exception("مجلد الفيديو غير موجود: {$directory}");
//             }
            
//             // عرض محتويات المجلد للتشخيص
//             $files = scandir($directory);
//             Log::info("📂 محتويات المجلد {$directory}: " . implode(', ', $files));
            
//             throw new \Exception("ملف الفيديو غير موجود: {$videoPath}");
//         }

//         // التحقق من حجم الملف
//         $fileSize = filesize($videoPath);
//         if ($fileSize === false) {
//             Log::error("❌ لا يمكن قراءة حجم الملف: {$videoPath}");
//             throw new \Exception("لا يمكن قراءة حجم ملف الفيديو: {$videoPath}");
//         }

//         if ($fileSize == 0) {
//             Log::error("❌ ملف الفيديو فارغ: {$videoPath}");
//             throw new \Exception("ملف الفيديو فارغ: {$videoPath}");
//         }

//         // التحقق من صلاحيات القراءة
//         if (!is_readable($videoPath)) {
//             Log::error("❌ ملف الفيديو غير قابل للقراءة: {$videoPath}");
//             $permissions = substr(sprintf('%o', fileperms($videoPath)), -4);
//             Log::error("📋 صلاحيات الملف: {$permissions}");
//             throw new \Exception("ملف الفيديو غير قابل للقراءة: {$videoPath} (الصلاحيات: {$permissions})");
//         }

//         // التحقق من نوع الملف
//         $mimeType = mime_content_type($videoPath);
//         $allowedTypes = [
//             'video/mp4', 
//             'video/quicktime', 
//             'video/x-msvideo', 
//             'video/x-ms-wmv', 
//             'video/webm', 
//             'video/ogg', 
//             'video/3gpp', 
//             'video/3gpp2'
//         ];

//         Log::info("📄 نوع الملف: " . ($mimeType ?: 'غير معروف'));

//         if ($mimeType === false) {
//             Log::warning("⚠️ لا يمكن تحديد نوع الملف، سيتم المتابعة بناءً على امتداد الملف");
//             // التحقق من الامتداد كخيار احتياطي
//             $extension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
//             $allowedExtensions = ['mp4', 'mov', 'avi', 'wmv', 'webm', 'ogg', '3gp'];
            
//             if (!in_array($extension, $allowedExtensions)) {
//                 throw new \Exception("امتداد الملف غير مدعوم: {$extension}");
//             }
//         } elseif (!in_array($mimeType, $allowedTypes)) {
//             Log::error("❌ نوع الملف غير مدعوم: {$mimeType}");
//             throw new \Exception("نوع الملف غير مدعوم: {$mimeType}");
//         }

//         Log::info("✅ تم التحقق من صحة ملف الفيديو: {$videoPath} - الحجم: " . $this->formatBytes($fileSize));
//     }

//     /**
//      * الحصول على معلومات الفيديو
//      */
//     private function getVideoInfo(string $videoPath): array
//     {
//         $fileSize = filesize($videoPath);
//         if ($fileSize === false) {
//             Log::warning("لا يمكن الحصول على حجم الملف: {$videoPath}");
//             return ['duration' => null, 'size' => null];
//         }

//         // محاولة الحصول على مدة الفيديو باستخدام getID3 إذا كان متوفراً
//         $duration = $this->getVideoDuration($videoPath);

//         Log::info("✅ تم الحصول على معلومات الفيديو للدرس: {$videoPath}");
        
//         return [
//             'duration' => $duration,
//             'size' => $fileSize
//         ];
//     }

//     /**
//      * محاولة الحصول على مدة الفيديو
//      */
//     private function getVideoDuration(string $videoPath): ?int
//     {
//         try {
//             // استخدام ffprobe إذا كان متوفراً
//             if (function_exists('exec')) {
//                 $command = "ffprobe -v quiet -show_entries format=duration -of csv=\"p=0\" " . escapeshellarg($videoPath);
//                 $output = null;
//                 $returnVar = null;
                
//                 exec($command, $output, $returnVar);
                
//                 if ($returnVar === 0 && !empty($output[0])) {
//                     return (int) round(floatval($output[0]));
//                 }
//             }
            
//             // إذا لم ينجح ffprobe، قم بإرجاع null
//             return null;
            
//         } catch (\Exception $e) {
//             Log::warning("لا يمكن الحصول على مدة الفيديو: " . $e->getMessage());
//             return null;
//         }
//     }

//     /**
//      * تنسيق الحجم بالبايت
//      */
//     private function formatBytes(int $bytes): string
//     {
//         $units = ['B', 'KB', 'MB', 'GB'];
//         for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
//             $bytes /= 1024;
//         }
//         return round($bytes, 2) . ' ' . $units[$i];
//     }

//     /**
//      * إنشاء المجلدات المطلوبة
//      */
//     private function createDirectories(string $outputDir): void
//     {
//         if (!is_dir($outputDir)) {
//             Log::info("📁 إنشاء مجلد الإخراج: {$outputDir}");
            
//             if (!mkdir($outputDir, 0755, true)) {
//                 throw new \Exception("فشل في إنشاء مجلد الإخراج: {$outputDir}");
//             }
            
//             Log::info("✅ تم إنشاء مجلد الإخراج: {$outputDir}");
//         } else {
//             Log::info("📂 مجلد الإخراج موجود بالفعل: {$outputDir}");
//         }
//     }

//     /**
//      * تنظيف الملفات المؤقتة
//      */
//     private function cleanup(): void
//     {
//         try {
//             $lesson = Lesson::find($this->lesson->id);

//             if ($lesson && $lesson->video_path) {
//                 // حذف الملف المؤقت إذا كان موجوداً
//                 if (strpos($lesson->video_path, 'temp_videos') !== false && Storage::exists($lesson->video_path)) {
//                     if (Storage::delete($lesson->video_path)) {
//                         Log::info("✅ تم حذف الملف المؤقت المرتبط بالدرس {$this->lesson->id}: {$lesson->video_path}");
//                     } else {
//                         Log::warning("⚠️ فشل في حذف الملف المؤقت المرتبط بالدرس {$this->lesson->id}: {$lesson->video_path}");
//                     }
//                 }

//                 // حذف مجلد المعالجة إذا كان فارغاً
//                 $outputDir = storage_path("app/private_videos/lesson_{$this->lesson->id}");
//                 if (is_dir($outputDir) && count(scandir($outputDir)) <= 2) { // فقط . و ..
//                     rmdir($outputDir);
//                     Log::info("✅ تم حذف مجلد المعالجة الفارغ: {$outputDir}");
//                 }
//             }
            
//         } catch (\Exception $e) {
//             Log::error("خطأ في تنظيف الملفات المؤقتة للدرس {$this->lesson->id}: " . $e->getMessage());
//         }
//     }
// }
