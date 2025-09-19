
# 🎥 دوكمنتيشن API الفيديو - أكاديمية الورد

## 📋 نظرة عامة

هذا الدليل يشرح كيفية استخدام API الفيديو في أكاديمية الورد، بما في ذلك رفع الفيديوهات، بثها، وحمايتها من التحميل غير المصرح.

## 🔐 المصادقة والأمان

جميع endpoints تتطلب مصادقة Bearer Token:
```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

### أنواع المستخدمين:
- **Admin**: يمكنه رفع وحذف الفيديوهات
- **Student**: يمكنه مشاهدة الفيديوهات المصرح له بها

## 📊 حالات الفيديو

| الحالة | الوصف | الإجراءات المتاحة |
|--------|-------|-------------------|
| `null` | لم يتم رفع فيديو | رفع فيديو جديد |
| `processing` | جاري المعالجة | متابعة التقدم |
| `ready` | جاهز للمشاهدة | بث الفيديو |
| `failed` | فشل في المعالجة | إعادة الرفع |

## 🎯 Endpoints الرئيسية

### 1. رفع فيديو (Admin Only)

```http
POST /api/lessons/{lesson_id}/video/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Parameters:**
- `video` (file, required): ملف الفيديو (mp4, mov, avi, wmv, webm)
- `is_protected` (boolean, optional): هل الفيديو محمي (افتراضي: true)

**Response:**
```json
{
  "success": true,
  "data": {
    "lesson_id": 1,
    "status": "ready",
    "upload_progress": 100,
    "message": "تم رفع ومعالجة الفيديو بنجاح",
    "video_stream_url": "/api/lessons/1/stream?token=abc123",
    "status_url": "/api/lessons/1/video/status"
  },
  "message": "تم رفع الفيديو بنجاح"
}
```

### 2. متابعة حالة المعالجة

```http
GET /api/lessons/{lesson_id}/video/status
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
    "has_video": false,
    "is_protected": true,
    "estimated_time_remaining": "حوالي 2-3 دقائق"
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
      "size": "245.8 MB",
      "duration_seconds": 2730,
      "size_bytes": 257698816
    },
    "stream_url": "/api/lessons/1/stream?token=xyz789"
  }
}
```

### 3. بث الفيديو

```http
GET /api/lessons/{lesson_id}/stream
Authorization: Bearer {token}
Range: bytes=0-1024 (اختياري)
```

**للفيديوهات المحمية:**
```http
GET /api/lessons/{lesson_id}/stream?token={video_token}
```

**Headers الاستجابة:**
```
Content-Type: video/mp4
Content-Length: 257698816
Accept-Ranges: bytes
Content-Range: bytes 0-1024/257698816
Cache-Control: no-cache, no-store, must-revalidate, private
X-Video-Protection: enabled
```

### 4. تجديد رمز الوصول

```http
POST /api/lessons/{lesson_id}/video/refresh-token
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "new_token_here",
    "expires_at": "2025-01-20T15:30:00Z",
    "stream_url": "/api/lessons/1/stream?token=new_token_here"
  },
  "message": "تم تجديد رمز الوصول بنجاح"
}
```

### 5. حذف الفيديو (Admin Only)

```http
DELETE /api/lessons/{lesson_id}/video
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [],
  "message": "تم حذف الفيديو بنجاح"
}
```

## 🛡️ نظام الحماية

### طبقات الحماية:

1. **Authentication**: Bearer Token مطلوب
2. **Authorization**: فحص صلاحيات الوصول للدرس
3. **Token Protection**: رموز مؤقتة للفيديوهات المحمية
4. **HTTP Headers**: منع التحميل والحماية من الـ hotlinking
5. **Range Requests**: دعم التشغيل المتقطع الآمن

### Headers الحماية:
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Content-Security-Policy: default-src 'self'; media-src 'self'
X-Robots-Tag: noindex, nofollow, nosnippet, noarchive
X-Video-Protection: enabled
Referrer-Policy: strict-origin-when-cross-origin
Content-Disposition: inline
```

## ⚡ التكامل مع React Frontend

### 1. رفع الفيديو

```javascript
const uploadVideo = async (lessonId, videoFile, isProtected = true) => {
  const formData = new FormData();
  formData.append('video', videoFile);
  formData.append('is_protected', isProtected);

  try {
    const response = await fetch(`/api/lessons/${lessonId}/video/upload`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      },
      body: formData
    });

    const result = await response.json();
    
    if (result.success) {
      console.log('تم رفع الفيديو بنجاح:', result.data);
      return result.data;
    } else {
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('خطأ في رفع الفيديو:', error);
    throw error;
  }
};
```

### 2. متابعة حالة المعالجة

```javascript
const checkVideoStatus = async (lessonId) => {
  try {
    const response = await fetch(`/api/lessons/${lessonId}/video/status`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    });

    const result = await response.json();
    return result.data;
  } catch (error) {
    console.error('خطأ في فحص حالة الفيديو:', error);
    throw error;
  }
};

// استخدام polling للمتابعة
const pollVideoStatus = (lessonId, callback) => {
  const interval = setInterval(async () => {
    try {
      const status = await checkVideoStatus(lessonId);
      callback(status);
      
      if (status.status === 'ready' || status.status === 'failed') {
        clearInterval(interval);
      }
    } catch (error) {
      console.error('خطأ في polling:', error);
      clearInterval(interval);
    }
  }, 2000); // فحص كل ثانيتين

  return interval;
};
```

### 3. عرض الفيديو

```javascript
import React, { useState, useEffect, useRef } from 'react';

const VideoPlayer = ({ lessonId }) => {
  const [videoData, setVideoData] = useState(null);
  const [error, setError] = useState(null);
  const videoRef = useRef(null);

  useEffect(() => {
    fetchVideoData();
  }, [lessonId]);

  const fetchVideoData = async () => {
    try {
      const response = await fetch(`/api/lessons/${lessonId}/video/status`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      });

      const result = await response.json();
      
      if (result.success && result.data.status === 'ready') {
        setVideoData(result.data);
      } else if (result.data.status === 'processing') {
        // بدء polling للمتابعة
        pollVideoStatus(lessonId, (status) => {
          if (status.status === 'ready') {
            setVideoData(status);
          }
        });
      }
    } catch (error) {
      setError('خطأ في تحميل الفيديو');
    }
  };

  const refreshToken = async () => {
    try {
      const response = await fetch(`/api/lessons/${lessonId}/video/refresh-token`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
        }
      });

      const result = await response.json();
      
      if (result.success) {
        setVideoData(prev => ({
          ...prev,
          stream_url: result.data.stream_url
        }));
      }
    } catch (error) {
      console.error('خطأ في تجديد الرمز:', error);
    }
  };

  if (error) {
    return <div className="error">{error}</div>;
  }

  if (!videoData || videoData.status !== 'ready') {
    return (
      <div className="video-loading">
        <p>جاري تحضير الفيديو...</p>
        {videoData && (
          <div className="progress-bar">
            <div 
              className="progress" 
              style={{ width: `${videoData.progress}%` }}
            ></div>
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="video-player">
      <video
        ref={videoRef}
        controls
        controlsList="nodownload"
        onContextMenu={(e) => e.preventDefault()}
        onError={() => refreshToken()}
        style={{ width: '100%', maxWidth: '800px' }}
      >
        <source 
          src={videoData.stream_url} 
          type="video/mp4" 
        />
        متصفحك لا يدعم عرض الفيديو.
      </video>
      
      {videoData.video_info && (
        <div className="video-info">
          <p>المدة: {videoData.video_info.duration}</p>
          <p>الحجم: {videoData.video_info.size}</p>
        </div>
      )}
    </div>
  );
};

export default VideoPlayer;
```

### 4. حماية إضافية في الفرونت إند

```javascript
// منع النقر بالزر الأيمن على الفيديو
const protectVideo = (videoElement) => {
  videoElement.addEventListener('contextmenu', (e) => {
    e.preventDefault();
    return false;
  });

  // منع اختصارات لوحة المفاتيح
  videoElement.addEventListener('keydown', (e) => {
    // منع Ctrl+S, Ctrl+A, F12, إلخ
    if (
      (e.ctrlKey && (e.key === 's' || e.key === 'a')) ||
      e.key === 'F12' ||
      (e.ctrlKey && e.shiftKey && e.key === 'I')
    ) {
      e.preventDefault();
      return false;
    }
  });

  // منع السحب والإفلات
  videoElement.addEventListener('dragstart', (e) => {
    e.preventDefault();
    return false;
  });
};
```

### 5. معالجة أخطاء الشبكة وانتهاء الرموز

```javascript
const handleVideoError = async (lessonId, setVideoData) => {
  try {
    // محاولة تجديد الرمز
    const response = await fetch(`/api/lessons/${lessonId}/video/refresh-token`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    });

    const result = await response.json();
    
    if (result.success) {
      setVideoData(prev => ({
        ...prev,
        stream_url: result.data.stream_url
      }));
      return true;
    }
  } catch (error) {
    console.error('فشل تجديد الرمز:', error);
  }
  
  return false;
};
```

## 🐛 معالجة الأخطاء

### أكواد الأخطاء الشائعة:

| كود | المعنى | الحل |
|-----|--------|------|
| 401 | غير مصرح | تسجيل دخول مطلوب |
| 403 | ممنوع | لا توجد صلاحية للوصول |
| 404 | غير موجود | الدرس أو الفيديو غير موجود |
| 413 | ملف كبير جداً | تقليل حجم الفيديو |
| 422 | بيانات خاطئة | فحص نوع وحجم الملف |
| 500 | خطأ خادم | المحاولة مرة أخرى |

### نصائح الاستكشاف:

1. **فحص الـ logs**: راجع logs الخادم لتفاصيل الأخطاء
2. **اختبار الاتصال**: تأكد من استقرار الاتصال بالإنترنت
3. **فحص الرموز**: تأكد من صلاحية رموز الوصول
4. **مراجعة الأذونات**: تأكد من أذونات الملفات والمجلدات

## 📈 مراقبة الأداء

### نصائح التحسين:

1. **استخدم CDN**: لتوزيع الفيديوهات عالمياً
2. **ضغط الفيديو**: قلل حجم الملفات قبل الرفع
3. **جودات متعددة**: وفر خيارات جودة مختلفة
4. **تخزين مؤقت ذكي**: للتحسين المتقدم

هذا النظام يوفر حماية شاملة للفيديوهات مع تجربة مستخدم ممتازة وأداء عالي.
