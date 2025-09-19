
# API Pagination Examples

## 1. Courses

### Get all courses (page 1)
```
GET /api/courses?page=1
```

### Get courses with search and pagination
```
GET /api/courses?page=2&search=برمجة&level=beginner&per_page=5
```

### Response format:
```json
{
  "data": [
    {
      "id": 1,
      "title": "كورس البرمجة",
      "description": "تعلم البرمجة من البداية",
      "price": 100.00,
      "image_url": "https://example.com/image.jpg",
      "is_active": true,
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/courses?page=1",
    "last": "http://localhost/api/courses?page=10",
    "prev": null,
    "next": "http://localhost/api/courses?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 10,
    "to": 10,
    "total": 100
  },
  "message": {
    "ar": "تم جلب الكورسات بنجاح",
    "en": "Courses retrieved successfully"
  }
}
```

## 2. Subscriptions

### Get all subscriptions
```
GET /api/subscriptions?page=1
```

### Get subscriptions with filters
```
GET /api/subscriptions?page=1&status=approved&search=أحمد
```

## 3. Users

### Get all users
```
GET /api/users?page=1
```

### Get users with filters
```
GET /api/users?page=2&role=student&gender=male&search=محمد
```

## 4. Lessons

### Get all public lessons
```
GET /api/lessons?page=1
```

### Get lessons by course
```
GET /api/lessons?page=1&course_id=1&search=مقدمة
```

## Available Parameters:

### All endpoints support:
- `page` - Page number (default: 1)
- `per_page` - Items per page (max: 50, default: 10)
- `search` - Search in relevant fields

### Specific filters:
- **Courses**: `level`, `language`, `grade`, `min_price`, `max_price`
- **Subscriptions**: `status` (pending, approved, rejected)
- **Users**: `role`, `gender`
- **Lessons**: `course_id`

## Example with curl:

```bash
# Get page 2 of courses
curl -X GET "http://localhost/api/courses?page=2" \
  -H "Accept: application/json"

# Get subscriptions with status filter
curl -X GET "http://localhost/api/subscriptions?page=1&status=approved" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```
