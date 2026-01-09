# DocShare API Documentation

## 🚀 Overview

DocShare API cung cấp RESTful endpoints để truy cập và quản lý tài liệu, users, và các tính năng khác. API hỗ trợ 2 phương thức xác thực:

1. **Session-based** (cho web app)
2. **API Key-based** (cho third-party, mobile apps)

## 📋 Base URL

```
Production: https://yourdomain.com/API/v1
Development: http://localhost/API/v1
```

## 🔐 Authentication

### Session-based (Web App)

Tự động sử dụng session cookie khi truy cập từ cùng domain. Không cần header đặc biệt.

```javascript
// Tự động dùng session
fetch('/API/v1/auth/profile')
  .then(r => r.json())
  .then(data => console.log(data));
```

### API Key-based (Third-party)

**QUAN TRỌNG**: Chỉ nhận API key qua header, KHÔNG qua query string.

#### Cách 1: Authorization Header (Bearer Token)

```javascript
fetch('/API/v1/api/documents', {
  headers: {
    'Authorization': 'Bearer your-api-key-here',
    'Content-Type': 'application/json'
  }
})
```

#### Cách 2: X-API-Key Header

```javascript
fetch('/API/v1/api/documents', {
  headers: {
    'X-API-Key': 'your-api-key-here',
    'Content-Type': 'application/json'
  }
})
```

#### Python Example

```python
import requests

headers = {
    'Authorization': 'Bearer your-api-key-here',
    'Content-Type': 'application/json'
}

response = requests.get('https://yourdomain.com/API/v1/api/documents', headers=headers)
data = response.json()
```

#### cURL Example

```bash
curl -H "Authorization: Bearer your-api-key-here" \
     -H "Content-Type: application/json" \
     https://yourdomain.com/API/v1/api/documents
```

---

## 📊 Response Format

Tất cả responses theo format chuẩn:

### Success Response

```json
{
  "success": true,
  "code": 200,
  "data": {
    // Response data here
  },
  "message": "Optional success message",
  "meta": {
    "request_id": "abc123...",
    "timestamp": "2024-01-01T12:00:00+07:00",
    "execution_time_ms": 45.23,
    "auth_type": "api_key"
  }
}
```

### Error Response

```json
{
  "success": false,
  "code": 400,
  "error": {
    "message": "Validation failed",
    "type": "Bad Request",
    "details": {
      "email": "Field 'email' is required",
      "password": "Field 'password' must be at least 6 characters"
    }
  },
  "meta": {
    "request_id": "abc123...",
    "timestamp": "2024-01-01T12:00:00+07:00",
    "execution_time_ms": 12.45
  }
}
```

---

## 📚 Endpoints

### Authentication (Session-based only)

#### `POST /auth/login`

Đăng nhập tạo session.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "username": "john_doe",
      "email": "user@example.com",
      "role": "user",
      "avatar_url": "/uploads/avatars/avatar.jpg"
    },
    "session_id": "abc123..."
  }
}
```

---

#### `POST /auth/logout`

Đăng xuất, destroy session.

**Response:**
```json
{
  "success": true,
  "data": {
    "logged_out": true
  }
}
```

---

#### `GET /auth/profile`

Lấy thông tin profile hiện tại (cần authentication).

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "john_doe",
    "email": "user@example.com",
    "avatar_url": "/uploads/avatars/avatar.jpg",
    "role": "user",
    "points": {
      "current": 1500,
      "total_earned": 5000,
      "total_spent": 3500
    },
    "stats": {
      "uploaded_documents": 10,
      "purchased_documents": 25
    }
  }
}
```

---

### Documents (Session hoặc API Key)

#### `GET /api/documents`

List documents với pagination và filters.

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number (>=1) |
| `limit` | int | 20 | Items per page (1-100) |
| `status` | enum | approved | `approved`, `pending`, `rejected`, `all` |
| `search` | string | - | Search in title/description |
| `category` | string | - | Filter by category code |
| `sort` | enum | newest | `newest`, `popular`, `downloads`, `price_asc`, `price_desc` |

**Example:**
```
GET /API/v1/api/documents?page=1&limit=20&status=approved&search=mathematics&sort=popular
```

**Response:**
```json
{
  "success": true,
  "data": {
    "documents": [
      {
        "id": 123,
        "title": "Advanced Mathematics.pdf",
        "description": "Complete math course",
        "uploader": {
          "id": 5,
          "username": "teacher123",
          "avatar_url": "/uploads/avatars/avatar.jpg"
        },
        "stats": {
          "views": 1500,
          "downloads": 300,
          "pages": 120
        },
        "price": 50,
        "is_free": false,
        "status": "approved",
        "thumbnail_url": "/uploads/thumbnails/thumb.jpg",
        "url": "/view?id=123",
        "created_at": "2024-01-01 10:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 150,
      "pages": 8
    },
    "filters": {
      "status": "approved",
      "search": "mathematics",
      "category": null,
      "sort": "popular"
    }
  }
}
```

---

#### `GET /api/documents/{id}`

Get single document details.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "title": "Advanced Mathematics.pdf",
    "description": "Complete math course",
    "uploader": {
      "id": 5,
      "username": "teacher123",
      "avatar_url": "/uploads/avatars/avatar.jpg"
    },
    "stats": {
      "views": 1500,
      "downloads": 300,
      "pages": 120
    },
    "price": 50,
    "is_free": false,
    "category": {
      "education_level": "university",
      "major": "CS",
      "subject": null
    },
    "permissions": {
      "can_view": true,
      "can_download": true
    },
    "view_url": "/view?id=123",
    "download_url": "/view?id=123&download=1",
    "created_at": "2024-01-01 10:00:00"
  }
}
```

---

## ⚠️ Error Codes

| Code | Type | Description |
|------|------|-------------|
| 400 | Bad Request | Invalid input/validation failed |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 405 | Method Not Allowed | HTTP method not supported |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

---

## 🚦 Rate Limiting

API Key có rate limiting theo cấu hình:

- **Default**: 100 requests/hour
- **Burst control**: 20 requests/minute
- **Response headers**:
  - `X-RateLimit-Remaining`: Requests còn lại
  - `X-RateLimit-Reset`: Timestamp khi reset

Khi vượt rate limit, response:

```json
{
  "success": false,
  "code": 429,
  "error": {
    "message": "Rate limit exceeded. Maximum 100 requests per hour.",
    "type": "Too Many Requests"
  }
}
```

---

## 🔑 API Key Management

### Tạo API Key (Admin Panel)

1. Vào `/admin/api-keys.php`
2. Điền thông tin:
   - User ID
   - Tên key
   - Mô tả
   - Permissions
   - Rate limit
   - Expires (optional)
   - IP whitelist (optional)
3. Click "Tạo API Key"
4. **LƯU Ý**: API key chỉ hiển thị 1 lần, hãy lưu ngay!

### Permissions

Format: `endpoint:action`

- `documents:read` - Đọc danh sách tài liệu
- `documents:write` - Tạo/cập nhật tài liệu
- `documents:delete` - Xóa tài liệu
- `users:read` - Đọc thông tin users
- `*` - Full access (chỉ dành cho admin)

### Revoke/Delete

- **Revoke**: Suspend key (có thể kích hoạt lại sau)
- **Delete**: Xóa vĩnh viễn (không thể khôi phục)

---

## 🔒 Security Best Practices

1. ✅ **KHÔNG bao giờ** đưa API key vào query string (`?api_key=...`)
2. ✅ **LUÔN** dùng HTTPS trong production
3. ✅ **Hash** API key khi lưu trong database
4. ✅ **Rotate** API keys định kỳ
5. ✅ **IP whitelist** cho server-to-server calls
6. ✅ **Rate limiting** để chống abuse
7. ✅ **Logging** tất cả requests để audit

---

## 📝 Examples

### JavaScript/Node.js

```javascript
const API_KEY = 'your-api-key-here';
const BASE_URL = 'https://yourdomain.com/API/v1';

async function getDocuments(page = 1) {
  const response = await fetch(`${BASE_URL}/api/documents?page=${page}`, {
    headers: {
      'Authorization': `Bearer ${API_KEY}`,
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  
  if (data.success) {
    console.log('Documents:', data.data.documents);
    console.log('Total:', data.data.pagination.total);
  } else {
    console.error('Error:', data.error.message);
  }
  
  return data;
}
```

### Python

```python
import requests

API_KEY = 'your-api-key-here'
BASE_URL = 'https://yourdomain.com/API/v1'

headers = {
    'Authorization': f'Bearer {API_KEY}',
    'Content-Type': 'application/json'
}

# Get documents
response = requests.get(f'{BASE_URL}/api/documents', headers=headers, params={
    'page': 1,
    'limit': 20,
    'status': 'approved',
    'sort': 'popular'
})

data = response.json()

if data['success']:
    documents = data['data']['documents']
    for doc in documents:
        print(f"{doc['title']} - {doc['price']} points")
else:
    print(f"Error: {data['error']['message']}")
```

---

## 🛠️ Setup Instructions

1. **Tạo database tables:**
   ```bash
   mysql -u root -p docshare < API/database_schema.sql
   ```

2. **Set environment variable:**
   ```env
   API_KEY_SECRET=your-very-secret-random-string-change-this
   ```

3. **Tạo API key đầu tiên:**
   - Vào `/admin/api-keys.php`
   - Tạo key với permissions phù hợp

4. **Test API:**
   ```bash
   curl -H "Authorization: Bearer YOUR_API_KEY" \
        https://yourdomain.com/API/v1/api/documents
   ```

---

## 📞 Support

Nếu có vấn đề, kiểm tra:
1. API key còn active và chưa hết hạn
2. Permissions đủ cho endpoint cần dùng
3. Rate limit chưa vượt
4. Request format đúng (method, headers, body)

**Request ID** trong response dùng để trace logs khi báo lỗi.
