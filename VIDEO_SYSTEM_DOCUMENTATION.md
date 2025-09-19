
# 🎥 نظام الفيديوهات المتقدم - Rose Academy

## 📋 نظرة عامة

نظام الفيديوهات في Rose Academy مصمم ليوفر حماية متقدمة للمحتوى التعليمي مع تجربة مستخدم سلسة. النظام يتضمن حماية ضد التحميل، معالجة تلقائية للفيديوهات، وبث آمن للمحتوى.

## 🏗️ معمارية النظام

### مكونات النظام الأساسية:

1. **LessonVideoController** - التحكم في رفع وبث الفيديوهات
2. **ProcessLessonVideo Job** - معالجة الفيديوهات في الخلفية
3. **PreventVideoDownload Middleware** - حماية الفيديوهات من التحميل
4. **Lesson Model** - إدارة بيانات الفيديوهات والحماية

## ⚙️ آلية عمل النظام

### 1. رفع الفيديو (Video Upload)

```
Admin -> Upload Video -> ProcessLessonVideo Job -> Video Ready
```

**الخطوات:**
1. المدير يرفع الفيديو عبر `/lessons/{id}/video/upload`
2. النظام يحفظ الفيديو مؤقتاً في `temp_videos`
3. يتم إنشاء `ProcessLessonVideo Job` في الـ queue
4. Job يعالج الفيديو وينقله للمجلد النهائي
5. يتم تحديث حالة الفيديو إلى `ready`

### 2. معالجة الفيديو (Video Processing)

**للفيديوهات المحمية:**
- نقل إلى `storage/app/private_videos/lesson_{id}/`
- إنشاء ملف `.htaccess` للحماية
- تعيين `is_video_protected = true`

**للفيديوهات غير المحمية:**
- نقل إلى `storage/app/public/videos/lesson_{id}/`
- إمكانية الوصول المباشر

### 3. بث الفيديو (Video Streaming)

```
User Request -> Authentication -> Authorization -> Token Validation -> Stream Video
```

## 🔐 نظام الحماية

### طبقات الحماية:

1. **Authentication** - التحقق من تسجيل الدخول
2. **Authorization** - التحقق من صلاحية الوصول للدرس
3. **Token-based Access** - رموز مؤقتة للفيديوهات المحمية
4. **Middleware Protection** - Headers متقدمة لمنع التحميل
5. **Range Request Support** - بث متقطع آمن

### رموز الحماية (Protection Tokens):

```php
// إنشاء رمز جديد
$token = $lesson->generateVideoToken(120); // صالح لمدة ساعتين

// التحقق من صحة الرمز
$isValid = $lesson->isValidVideoToken($token);
```

## 📊 حالات الفيديو (Video States)

| الحالة | الوصف | الإجراءات المتاحة |
|--------|-------|-------------------|
| `null` | لم يتم رفع فيديو | رفع فيديو جديد |
| `processing` | جاري المعالجة | متابعة التقدم |
| `ready` | جاهز للمشاهدة | بث الفيديو |
| `failed` | فشل في المعالجة | إعادة الرفع |

## 🛡️ Headers الحماية

```php
'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
'X-Content-Type-Options' => 'nosniff',
'X-Frame-Options' => 'SAMEORIGIN',
'Content-Security-Policy' => "default-src 'self'; media-src 'self'",
'X-Robots-Tag' => 'noindex, nofollow, nosnippet, noarchive',
'X-Video-Protection' => 'enabled',
'Content-Disposition' => 'inline; filename=""'
```

## 📱 استخدام API

### 1. رفع فيديو (Admin Only)

```http
POST /api/lessons/{id}/video/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

video: [video_file]
is_protected: true/false
```

**Response:**
```json
{
  "success": true,
  "data": {
    "lesson_id": 1,
    "status": "processing",
    "upload_progress": 100,
    "processing_progress": 0,
    "message": "تم رفع الفيديو بنجاح، وجاري معالجته...",
    "status_url": "/api/lessons/1/video/status"
  }
}
```

### 2. متابعة حالة المعالجة

```http
GET /api/lessons/{id}/video/status
Authorization: Bearer {token}
```

**Response (Processing):**
```json
{
  "success": true,
  "data": {
    "lesson_id": 1,
    "status": "processing",
    "progress": 65,
    "message": "جاري معالجة الفيديو...",
    "estimated_time_remaining": "حوالي 2-3 دقائق",
    "processing_steps": [
      {
        "step": "تحميل الفيديو",
        "status": "completed"
      },
      {
        "step": "فحص الملف",
        "status": "completed"
      },
      {
        "step": "تطبيق الحماية",
        "status": "in_progress"
      }
    ]
  }
}
```

**Response (Ready):**
```json
{
  "success": true,
  "data": {
    "lesson_id": 1,
    "status": "ready",
    "progress": 100,
    "has_video": true,
    "is_protected": true,
    "video_info": {
      "duration": "00:45:30",
      "size": "245.8 MB"
    },
    "stream_url": "/api/lessons/1/stream?token={generated_token}"
  }
}
```

### 3. تجديد رمز الوصول

```http
POST /api/lessons/{id}/video/refresh-token
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_at": "2025-01-20T14:30:00Z",
    "stream_url": "/api/lessons/1/stream?token={new_token}"
  }
}
```

### 4. بث الفيديو

```http
GET /api/lessons/{id}/stream?token={video_token}
Authorization: Bearer {auth_token}
Range: bytes=0-1024 (اختياري)
```

**Headers:**
- `Range: bytes=start-end` - للبث المتقطع
- `Authorization: Bearer {token}` - مطلوب دائماً

## 🔍 مراقبة النظام

### Logs المهمة:

```php
// رفع الفيديو
Log::info("تم رفع فيديو للدرس: {$lesson->id}", [
    'user_id' => $user->id,
    'file_size' => $fileSize,
    'is_protected' => $isProtected
]);

// بث الفيديو
Log::info('Video stream accessed', [
    'lesson_id' => $lesson->id,
    'user_id' => $user->id,
    'session_id' => $sessionId,
    'ip_address' => $request->ip(),
    'is_protected' => $lesson->is_video_protected
]);
```

### متابعة الأداء:

1. **Queue Status**: `php artisan queue:work --queue=video-processing`
2. **Storage Usage**: مراقبة مساحة `storage/app/`
3. **Processing Time**: متوسط وقت معالجة الفيديوهات
4. **Failed Jobs**: `php artisan queue:failed`

## 🚨 استكشاف الأخطاء

### مشاكل شائعة:

1. **فشل رفع الفيديو**
   - تحقق من حجم الملف (أقصى حد 2GB)
   - تأكد من نوع الملف المدعوم
   - فحص مساحة التخزين المتاحة

2. **فشل معالجة الفيديو**
   - تحقق من تشغيل Queue Worker
   - فحص صلاحيات مجلدات storage
   - مراجعة logs الخطأ

3. **مشاكل البث**
   - التحقق من صحة رمز الوصول
   - فحص الاشتراك النشط
   - مراجعة إعدادات الحماية

## 📈 تحسينات مستقبلية

1. **ضغط الفيديو**: تقليل حجم الملفات تلقائياً
2. **جودات متعددة**: 720p, 1080p, 4K
3. **CDN Integration**: توزيع المحتوى عالمياً
4. **Analytics**: إحصائيات مشاهدة متقدمة
5. **Watermarking**: إضافة علامات مائية تلقائية

## ⚡ أفضل الممارسات

1. **Queue Workers**: تشغيل عدة workers للمعالجة السريعة
2. **Storage Management**: تنظيف الملفات المؤقتة دورياً
3. **Token Management**: مراقبة انتهاء صلاحية الرموز
4. **Error Handling**: معالجة شاملة للأخطاء
5. **Security Updates**: تحديث إعدادات الحماية باستمرار

---

هذا النظام مصمم لحماية المحتوى التعليمي مع الحفاظ على تجربة مستخدم ممتازة وأداء عالي.
