# API Документация - AI Tools Platform

## Базов URL

```
http://localhost:8201/api
```

## Автентикация

API използва session-based автентикация. Потребителят трябва да се логне чрез `/api/login` endpoint преди да използва защитените endpoints.

### Login

```http
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com",
    "role": "backend",
    "display_role": "backend",
    "status": "approved"
  },
  "message": "Login successful"
}
```

---

## AI Tools Endpoints

### 1. Списък с AI инструменти

```http
GET /api/tools
```

**Query Parameters:**
- `status` (optional) - Филтриране по статус: `active`, `inactive`, `pending_review`
- `category_id` (optional) - Филтриране по категория ID
- `role` (optional) - Филтриране по роля: `backend`, `frontend`, `qa`, `pm`, `designer`
- `featured` (optional) - Филтриране по featured: `true` или `false`
- `search` (optional) - Търсене в name, description, short_description
- `sort_by` (optional) - Сортиране по: `created_at`, `name`, `views_count`, `likes_count`, `updated_at` (default: `created_at`)
- `sort_order` (optional) - Посока: `asc` или `desc` (default: `desc`)
- `per_page` (optional) - Брой резултати на страница: 1-100 (default: 15)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "ChatGPT",
      "slug": "chatgpt",
      "description": "AI-powered conversational assistant",
      "short_description": "Advanced AI chatbot",
      "url": "https://chat.openai.com",
      "logo_url": "https://example.com/logo.png",
      "pricing_model": "freemium",
      "status": "active",
      "featured": true,
      "views_count": 1500,
      "likes_count": 120,
      "created_at": "2025-01-17T10:00:00.000000Z",
      "updated_at": "2025-01-17T10:00:00.000000Z",
      "creator": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "categories": [
        {
          "id": 1,
          "name": "Text Processing",
          "slug": "text-processing"
        }
      ],
      "roles": ["backend", "frontend"]
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 67
  }
}
```

**Пример:**
```http
GET /api/tools?category_id=1&role=backend&search=code&sort_by=views_count&sort_order=desc&per_page=20
```

---

### 2. Детайли за AI инструмент

```http
GET /api/tools/{slug}
```

**Path Parameters:**
- `slug` - Slug или ID на инструмента

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "ChatGPT",
    "slug": "chatgpt",
    "description": "AI-powered conversational assistant",
    "short_description": "Advanced AI chatbot",
    "url": "https://chat.openai.com",
    "logo_url": "https://example.com/logo.png",
    "pricing_model": "freemium",
    "status": "active",
    "featured": true,
    "views_count": 1501,
    "likes_count": 120,
    "documentation_url": "https://docs.openai.com",
    "github_url": null,
    "tags": ["ai", "chat", "nlp"],
    "created_at": "2025-01-17T10:00:00.000000Z",
    "updated_at": "2025-01-17T10:00:00.000000Z",
    "creator": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "updater": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "categories": [
      {
        "id": 1,
        "name": "Text Processing",
        "slug": "text-processing"
      }
    ],
    "roles": ["backend", "frontend"],
    "is_liked": false,
    "average_rating": 4.5,
    "reviews_count": 25
  }
}
```

**Забележка:** `views_count` се увеличава автоматично при всяко заявяване.

---

### 3. Създаване на AI инструмент

```http
POST /api/tools
Authorization: Required (approved users only)
```

**Request Body:**
```json
{
  "name": "New AI Tool",
  "description": "Full description of the tool",
  "short_description": "Short description (max 500 chars)",
  "url": "https://example.com",
  "logo_url": "https://example.com/logo.png",
  "pricing_model": "free",
  "status": "pending_review",
  "featured": false,
  "category_ids": [1, 2],
  "roles": ["backend", "frontend"],
  "tags": ["ai", "code"],
  "documentation_url": "https://docs.example.com",
  "github_url": "https://github.com/example"
}
```

**Required Fields:**
- `name` (string, max 255)
- `url` (valid URL, max 500)
- `pricing_model` (enum: `free`, `freemium`, `paid`, `enterprise`)

**Optional Fields:**
- `description` (string)
- `short_description` (string, max 500)
- `logo_url` (valid URL, max 500)
- `status` (enum: `active`, `inactive`, `pending_review`) - Default: `pending_review` (except for owners)
- `featured` (boolean) - Default: `false`
- `category_ids` (array of category IDs)
- `roles` (array: `backend`, `frontend`, `qa`, `pm`, `designer`)
- `tags` (array)
- `documentation_url` (valid URL, max 500)
- `github_url` (valid URL, max 500)

**Response (201 Created):**
```json
{
  "success": true,
  "message": "AI tool created successfully.",
  "data": {
    "id": 10,
    "name": "New AI Tool",
    "slug": "new-ai-tool",
    ...
  }
}
```

**Error Responses:**
- `401 Unauthorized` - Не е автентифициран
- `403 Forbidden` - Акаунтът не е одобрен
- `422 Unprocessable Entity` - Валидационни грешки

---

### 4. Редактиране на AI инструмент

```http
PUT /api/tools/{slug}
Authorization: Required (owner or creator only)
```

**Path Parameters:**
- `slug` - Slug или ID на инструмента

**Request Body:** (всички полета са optional, използва се `sometimes` валидация)
```json
{
  "name": "Updated Tool Name",
  "description": "Updated description",
  "url": "https://updated-url.com",
  "pricing_model": "paid",
  "category_ids": [2, 3],
  "roles": ["backend"]
}
```

**Response:**
```json
{
  "success": true,
  "message": "AI tool updated successfully.",
  "data": {
    "id": 10,
    "name": "Updated Tool Name",
    ...
  }
}
```

**Забележки:**
- Само owner може да променя `status` и `featured`
- Creator може да редактира само своите инструменти
- Slug се обновява автоматично ако `name` се промени

---

### 5. Изтриване на AI инструмент

```http
DELETE /api/tools/{slug}
Authorization: Required (owner or creator only)
```

**Path Parameters:**
- `slug` - Slug или ID на инструмента

**Response:**
```json
{
  "success": true,
  "message": "AI tool deleted successfully."
}
```

**Забележка:** Използва се soft delete - инструментът не се изтрива перманентно.

---

### 6. Like/Unlike AI инструмент

```http
POST /api/tools/{slug}/like
Authorization: Required
Rate Limit: 10 requests per minute
```

**Path Parameters:**
- `slug` - Slug или ID на инструмента

**Response:**
```json
{
  "success": true,
  "message": "Tool liked.",
  "data": {
    "liked": true,
    "likes_count": 121
  }
}
```

**Забележки:**
- Ако инструментът вече е лайкнат, той се unlike-ва
- Rate limited до 10 заявки на минута за защита срещу spam

---

## Categories Endpoints

### 1. Списък с категории

```http
GET /api/categories
```

**Query Parameters:**
- `active` (optional) - Филтриране по активност: `true` или `false` (default: `true`)
- `parent_id` (optional) - Филтриране по родител (или `null` за root категории)
- `with_counts` (optional) - Включване на брой инструменти: `true` или `false`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Code Generation",
      "slug": "code-generation",
      "description": "Tools for generating code",
      "icon": "💻",
      "color": "#3B82F6",
      "parent_id": null,
      "order": 1,
      "is_active": true,
      "tools_count": 15
    }
  ]
}
```

---

### 2. Детайли за категория

```http
GET /api/categories/{slug}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Code Generation",
    "slug": "code-generation",
    "description": "Tools for generating code",
    "icon": "💻",
    "color": "#3B82F6",
    "parent_id": null,
    "order": 1,
    "is_active": true,
    "parent": null,
    "children": [],
    "tools_count": 15
  }
}
```

---

### 3. Създаване на категория

```http
POST /api/categories
Authorization: Required (owner only)
```

**Request Body:**
```json
{
  "name": "New Category",
  "description": "Category description",
  "icon": "🎨",
  "color": "#FF5733",
  "parent_id": null,
  "order": 0,
  "is_active": true
}
```

---

### 4. Редактиране на категория

```http
PUT /api/categories/{slug}
Authorization: Required (owner only)
```

---

### 5. Изтриване на категория

```http
DELETE /api/categories/{slug}
Authorization: Required (owner only)
```

**Забележки:**
- Не може да се изтрие категория с асоциирани инструменти
- Не може да се изтрие категория с подкатегории

---

## Reviews Endpoints

### 1. Списък с ревюта за инструмент

```http
GET /api/tools/{slug}/reviews
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "rating": 5,
      "comment": "Great tool!",
      "user": {
        "id": 1,
        "name": "John Doe"
      },
      "created_at": "2025-01-17T10:00:00.000000Z"
    }
  ]
}
```

---

### 2. Статистики за ревюта

```http
GET /api/tools/{slug}/reviews/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "average_rating": 4.5,
    "total_reviews": 25,
    "rating_distribution": {
      "5": 10,
      "4": 8,
      "3": 5,
      "2": 1,
      "1": 1
    }
  }
}
```

---

### 3. Създаване на ревю

```http
POST /api/tools/{slug}/reviews
Authorization: Required
```

**Request Body:**
```json
{
  "rating": 5,
  "comment": "Excellent tool for development!"
}
```

---

## Error Responses

### Стандартен формат на грешки:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": [
      "The field name is required."
    ]
  }
}
```

### HTTP Status Codes:

- `200 OK` - Успешна заявка
- `201 Created` - Успешно създаване
- `401 Unauthorized` - Не е автентифициран
- `403 Forbidden` - Няма права за операцията
- `404 Not Found` - Ресурсът не е намерен
- `422 Unprocessable Entity` - Валидационни грешки
- `429 Too Many Requests` - Rate limit е превишен
- `500 Internal Server Error` - Сървърна грешка

---

## Rate Limiting

Някои endpoints имат rate limiting:
- `/api/tools/{slug}/like` - 10 заявки на минута на потребител

При превишаване на лимита се връща `429 Too Many Requests`.

---

## Pagination

Endpoints, които връщат списъци, поддържат pagination:

**Query Parameters:**
- `per_page` - Брой резултати на страница (1-100, default: 15)
- `page` - Номер на страницата (default: 1)

**Response Format:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 67
  }
}
```

---

## Best Practices

1. **Винаги проверявай HTTP status кода** преди да обработваш response
2. **Използвай pagination** за големи списъци
3. **Кеширай responses** където е възможно
4. **Обработвай rate limiting** с retry логика
5. **Валидирай данните** преди изпращане
6. **Използвай slug вместо ID** където е възможно

---

**Последна актуализация:** Януари 2025

