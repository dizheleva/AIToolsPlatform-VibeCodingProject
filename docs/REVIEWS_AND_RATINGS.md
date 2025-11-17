# Reviews and Ratings Feature

## Общ преглед

Функционалността за коментари и рейтинг позволява на потребителите да оставят ревюта и оценки (1-5 звезди) за AI инструменти.

## Функционалности

### Основни функции

1. **Създаване на ревю**
   - Рейтинг от 1 до 5 звезди (задължително)
   - Коментар (опционално, макс. 2000 символа)
   - Един потребител може да направи само едно ревю за инструмент

2. **Редактиране на ревю**
   - Потребителят може да редактира собственото си ревю
   - Може да променя рейтинга и/или коментара

3. **Изтриване на ревю**
   - Потребителят може да изтрие собственото си ревю
   - Администратор (owner) може да изтрие всяко ревю

4. **Преглед на ревюта**
   - Публичен достъп до всички ревюта
   - Филтриране по минимален рейтинг
   - Сортиране по дата (най-нови/най-стари)
   - Pagination

5. **Статистики**
   - Среден рейтинг
   - Общ брой ревюта
   - Разпределение по рейтинги (1-5 звезди)

## Database Schema

### Таблица: `tool_reviews`

```sql
- id (bigint, primary key)
- ai_tool_id (bigint, foreign key -> ai_tools.id)
- user_id (bigint, foreign key -> users.id)
- rating (tinyint, 1-5)
- comment (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)
- deleted_at (timestamp, nullable) - soft delete

Indexes:
- UNIQUE (ai_tool_id, user_id) - един потребител, едно ревю за инструмент
- INDEX (ai_tool_id)
- INDEX (user_id)
- INDEX (rating)
- INDEX (created_at)
```

## API Endpoints

### Публични endpoints (без автентикация)

```
GET /api/tools/{slug}/reviews
GET /api/tools/{slug}/reviews/statistics
```

### Защитени endpoints (изискват автентикация)

```
POST   /api/tools/{slug}/reviews          - Създаване на ревю
PUT    /api/tools/{slug}/reviews/{id}      - Редактиране на ревю
DELETE /api/tools/{slug}/reviews/{id}      - Изтриване на ревю
```

## Request/Response примери

### Създаване на ревю

**Request:**
```http
POST /api/tools/chatgpt/reviews
Authorization: Bearer {token}
Content-Type: application/json

{
  "rating": 5,
  "comment": "Отличен инструмент! Използвам го ежедневно за разработка."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Review created successfully.",
  "data": {
    "id": 1,
    "ai_tool_id": 1,
    "user_id": 5,
    "rating": 5,
    "comment": "Отличен инструмент! Използвам го ежедневно за разработка.",
    "created_at": "2025-01-17T10:30:00.000000Z",
    "updated_at": "2025-01-17T10:30:00.000000Z",
    "user": {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com"
    }
  }
}
```

### Преглед на ревюта

**Request:**
```http
GET /api/tools/chatgpt/reviews?per_page=10&min_rating=4&sort_by=created_at&sort_order=desc
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "rating": 5,
      "comment": "Отличен инструмент!",
      "created_at": "2025-01-17T10:30:00.000000Z",
      "user": {
        "id": 5,
        "name": "John Doe"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  },
  "average_rating": 4.5,
  "reviews_count": 25
}
```

### Статистики

**Request:**
```http
GET /api/tools/chatgpt/reviews/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_reviews": 25,
    "average_rating": 4.5,
    "rating_distribution": {
      "5": 15,
      "4": 7,
      "3": 2,
      "2": 1,
      "1": 0
    }
  }
}
```

## Права и ограничения

### Създаване на ревю
- ✅ Изисква автентикация
- ✅ Изисква одобрен статус (`status: approved`)
- ⚠️ Един потребител може да направи само едно ревю за инструмент

### Редактиране на ревю
- ✅ Само собственикът на ревюто може да го редактира

### Изтриване на ревю
- ✅ Собственикът на ревюто
- ✅ Администратор (owner)

### Преглед на ревюта
- ✅ Публичен достъп (не изисква автентикация)

## Интеграция в модели

### AiTool модел

Добавени методи:
- `reviews()` - връзка към ревютата
- `average_rating` - среден рейтинг (accessor)
- `reviews_count` - брой ревюта (accessor)

### User модел

Добавен метод:
- `reviews()` - връзка към ревютата на потребителя

### ToolReview модел

Нов модел с:
- `tool()` - връзка към инструмента
- `user()` - връзка към потребителя
- Scopes: `withRating()`, `recent()`

## Валидация

### Създаване/Редактиране на ревю

- `rating`: задължително, integer, между 1 и 5
- `comment`: опционално, string, макс. 2000 символа

## Използване във Frontend

### Пример за компонент

```typescript
// Fetch reviews
const response = await fetch(`/api/tools/${slug}/reviews`);
const { data, average_rating, reviews_count } = await response.json();

// Create review
const createReview = async (rating: number, comment: string) => {
  const response = await fetch(`/api/tools/${slug}/reviews`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({ rating, comment })
  });
  return response.json();
};
```

## Миграция

За да активираш функционалността:

```bash
docker compose exec php_fpm php artisan migrate
```

## Бъдещи подобрения

1. **Модерация на ревюта**
   - Флагване на неподходящи ревюта
   - Автоматична модерация

2. **Полезност на ревюта**
   - "Полезно/Не полезно" бутони
   - Сортиране по полезност

3. **Отговори на ревюта**
   - Създателите на инструменти могат да отговарят на ревюта

4. **Верификация**
   - Верифицирани ревюта от потребители, които са използвали инструмента

5. **Филтри**
   - Филтриране по дата
   - Филтриране по рейтинг
   - Филтриране по потребител

---

**Добавено:** 17 януари 2025  
**Версия:** 1.0.0

