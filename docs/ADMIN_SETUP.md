# Настройка на админ потребител

## Проблем: "Invalid credentials" при логин

Ако получаваш "Invalid credentials" при опит за логин с админ акаунт, най-вероятно потребителят не съществува в базата данни или паролата не е правилна.

## Решение 1: Използвай Artisan команда (Препоръчително)

Създадена е Artisan команда за лесно създаване/актуализация на админ потребител:

```bash
# Създай админ с default данни
docker compose exec php_fpm php artisan user:create-admin

# Или с custom данни
docker compose exec php_fpm php artisan user:create-admin --email=admin@example.com --password=mysecurepassword --name="Админ Име"
```

**Default данни:**
- Email: `admin@admin.local`
- Password: `admin123`
- Role: `owner`
- Status: `approved`

## Решение 2: Изпълни Database Seeder

```bash
# Изпълни seeder-а, който създава админ потребител
docker compose exec php_fpm php artisan db:seed --class=DatabaseSeeder
```

Това ще създаде:
- **Админ акаунт:**
  - Email: `ivan@admin.local`
  - Password: `password`
  - Role: `owner`
  - Status: `approved`

## Решение 3: Ръчно създаване чрез Tinker

```bash
docker compose exec php_fpm php artisan tinker
```

След това в tinker:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::updateOrCreate(
    ['email' => 'admin@admin.local'],
    [
        'name' => 'Админ Потребител',
        'password' => Hash::make('admin123'),
        'role' => 'owner',
        'status' => 'approved',
        'email_verified_at' => now(),
    ]
);
```

## Решение 4: Директно в базата данни

Ако предпочиташ да създадеш потребителя директно в базата:

```bash
# Влез в MySQL
docker compose exec mysql mysql -u root -pvibecode-full-stack-starter-kit_mysql_pass vibecode-full-stack-starter-kit_app
```

След това SQL команда:

```sql
INSERT INTO users (name, email, password, role, status, email_verified_at, created_at, updated_at)
VALUES (
    'Админ Потребител',
    'admin@admin.local',
    '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyYqF5q5q5q5', -- парола: admin123
    'owner',
    'approved',
    NOW(),
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    status = 'approved',
    role = 'owner';
```

**Забележка:** За да генерираш правилен hash за паролата, използвай:

```bash
docker compose exec php_fpm php artisan tinker
```

```php
echo Hash::make('admin123');
```

## Проверка на съществуващи потребители

За да видиш всички потребители в базата:

```bash
docker compose exec php_fpm php artisan tinker
```

```php
use App\Models\User;

// Всички потребители
User::all(['id', 'name', 'email', 'role', 'status'])->toArray();

// Проверка на конкретен потребител
$user = User::where('email', 'admin@admin.local')->first();
if ($user) {
    echo "User exists: {$user->name} ({$user->email})\n";
    echo "Role: {$user->role}\n";
    echo "Status: {$user->status}\n";
} else {
    echo "User not found!\n";
}
```

## Тестване на логин

След като създадеш админ потребител, можеш да тестваш логина:

### Чрез API:

```bash
curl -X POST http://localhost:8201/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@admin.local",
    "password": "admin123"
  }'
```

### Чрез Frontend:

Отиди на `http://localhost:8200/login` и използвай:
- Email: `admin@admin.local`
- Password: `admin123`

## Често срещани проблеми

### 1. "Invalid credentials"
- **Причина:** Потребителят не съществува или паролата е грешна
- **Решение:** Изпълни `php artisan user:create-admin` или `php artisan db:seed`

### 2. "Your account is pending approval"
- **Причина:** Потребителят съществува, но статусът е `pending`
- **Решение:** Актуализирай статуса на `approved`:
  ```php
  User::where('email', 'admin@admin.local')->update(['status' => 'approved']);
  ```

### 3. Паролата не работи след seed
- **Причина:** Паролата може да е била хеширана различно
- **Решение:** Изпълни `php artisan user:create-admin` за да актуализираш паролата

## Препоръчителни данни за админ

За production среда, използвай силна парола:

```bash
docker compose exec php_fpm php artisan user:create-admin \
  --email=admin@yourcompany.com \
  --password=YourVerySecurePassword123! \
  --name="Админ Име"
```

## Безопасност

⚠️ **Важно:** 
- Винаги използвай силни пароли в production
- Не споделяй админ credentials публично
- Регулярно сменяй паролите
- Използвай 2FA за допълнителна сигурност

---

**Създадено:** 17 януари 2025

