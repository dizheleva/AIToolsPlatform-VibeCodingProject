# Ръководство за тестване

## Стартиране на тестове

### В Docker контейнер

```bash
# Всички тестове
docker compose exec php_fpm php artisan test

# Само Feature тестове
docker compose exec php_fpm php artisan test --testsuite=Feature

# Само Unit тестове
docker compose exec php_fpm php artisan test --testsuite=Unit

# Конкретен тест клас
docker compose exec php_fpm php artisan test --filter=AiToolControllerTest

# Конкретен тест метод
docker compose exec php_fpm php artisan test --filter=AiToolControllerTest::it_can_list_ai_tools
```

### Локално (ако имаш PHP инсталиран)

```bash
cd backend
php artisan test
```

## Налични тестове

### Feature тестове

#### AiToolControllerTest
- ✅ `it_can_list_ai_tools` - Тест за списък с инструменти
- ✅ `it_can_filter_tools_by_status` - Филтриране по статус
- ✅ `it_can_filter_tools_by_category` - Филтриране по категория
- ✅ `it_can_search_tools` - Търсене в инструменти
- ✅ `it_can_show_single_tool` - Показване на един инструмент
- ✅ `it_requires_authentication_to_create_tool` - Автентикация за създаване
- ✅ `it_requires_approved_status_to_create_tool` - Одобрен статус за създаване
- ✅ `approved_user_can_create_tool` - Одобрен потребител може да създава
- ✅ `owner_can_create_tool_with_active_status` - Owner може да създава с active статус
- ✅ `it_validates_required_fields_when_creating` - Валидация на задължителни полета
- ✅ `creator_can_update_their_tool` - Creator може да редактира своя инструмент
- ✅ `owner_can_update_any_tool` - Owner може да редактира всеки инструмент
- ✅ `user_cannot_update_other_users_tool` - Потребител не може да редактира чужди инструменти
- ✅ `creator_can_delete_their_tool` - Creator може да изтрива своя инструмент
- ✅ `authenticated_user_can_like_tool` - Автентифициран потребител може да лайква
- ✅ `user_can_unlike_tool` - Потребител може да unlike-ва
- ✅ `it_respects_rate_limiting_on_like` - Rate limiting работи

#### CategoryControllerTest
- ✅ `it_can_list_categories` - Тест за списък с категории
- ✅ `it_shows_only_active_categories_by_default` - Показва само активни категории по подразбиране
- ✅ `it_can_filter_categories_by_active_status` - Филтриране по активност
- ✅ `it_can_filter_categories_by_parent_id` - Филтриране по родител
- ✅ `it_can_filter_root_categories` - Филтриране на root категории
- ✅ `it_can_include_tools_count` - Включване на брой инструменти
- ✅ `it_can_show_single_category` - Показване на една категория
- ✅ `it_requires_authentication_to_create_category` - Автентикация за създаване
- ✅ `it_requires_owner_role_to_create_category` - Owner роля за създаване
- ✅ `owner_can_create_category` - Owner може да създава категории
- ✅ `it_validates_required_fields_when_creating` - Валидация на задължителни полета
- ✅ `it_validates_color_format` - Валидация на цветен формат
- ✅ `it_validates_parent_id_exists` - Валидация на parent_id
- ✅ `owner_can_update_category` - Owner може да редактира категории
- ✅ `it_updates_slug_when_name_changes` - Обновяване на slug при промяна на име
- ✅ `it_prevents_circular_reference_in_parent_id` - Защита срещу circular reference
- ✅ `non_owner_cannot_update_category` - Не-owner не може да редактира
- ✅ `owner_can_delete_category` - Owner може да изтрива категории
- ✅ `it_prevents_deleting_category_with_tools` - Защита срещу изтриване с инструменти
- ✅ `it_prevents_deleting_category_with_children` - Защита срещу изтриване с подкатегории
- ✅ `non_owner_cannot_delete_category` - Не-owner не може да изтрива

#### ToolReviewControllerTest
- ✅ `it_can_list_reviews_for_a_tool` - Тест за списък с ревюта
- ✅ `it_can_filter_reviews_by_min_rating` - Филтриране по минимален рейтинг
- ✅ `it_can_sort_reviews_by_rating` - Сортиране по рейтинг
- ✅ `it_validates_sort_by_against_whitelist` - Валидация на sort_by срещу whitelist
- ✅ `it_validates_per_page_limits` - Валидация на per_page лимити
- ✅ `it_requires_authentication_to_create_review` - Автентикация за създаване
- ✅ `it_requires_approved_status_to_create_review` - Одобрен статус за създаване
- ✅ `approved_user_can_create_review` - Одобрен потребител може да създава
- ✅ `it_validates_required_fields_when_creating` - Валидация на задължителни полета
- ✅ `it_validates_rating_range` - Валидация на диапазон на рейтинг
- ✅ `it_validates_comment_max_length` - Валидация на максимална дължина на коментар
- ✅ `it_prevents_duplicate_reviews` - Защита срещу дублирани ревюта
- ✅ `user_can_update_their_own_review` - Потребител може да редактира своето ревю
- ✅ `user_cannot_update_other_users_review` - Потребител не може да редактира чужди ревюта
- ✅ `it_validates_review_belongs_to_tool` - Валидация че ревюто принадлежи на инструмента
- ✅ `user_can_delete_their_own_review` - Потребител може да изтрива своето ревю
- ✅ `owner_can_delete_any_review` - Owner може да изтрива всяко ревю
- ✅ `user_cannot_delete_other_users_review` - Потребител не може да изтрива чужди ревюта
- ✅ `it_can_get_review_statistics` - Статистика за ревюта
- ✅ `statistics_calculates_average_rating_correctly` - Правилно изчисляване на среден рейтинг

### Unit тестове

#### AiToolPolicyTest
- ✅ Тестове за всички Policy методи
- ✅ Тестове за права на различни роли
- ✅ Тестове за view, create, update, delete права

#### StoreAiToolRequestTest
- ✅ Тестове за валидация на всички полета
- ✅ Тестове за required полета
- ✅ Тестове за enum валидация
- ✅ Тестове за URL валидация

## Конфигурация на тестове

Тестовете използват:
- **Database**: SQLite in-memory (`:memory:`)
- **Cache**: Array driver
- **Queue**: Sync driver (синхронно изпълнение)
- **Session**: Array driver

Всички тестове използват `RefreshDatabase` trait, което означава, че базата данни се рефрешва преди всеки тест.

## Проблеми и решения

### Тестовете са бавни
- Провери дали има проблеми с базата данни
- Увеличи timeout в phpunit.xml ако е необходимо

### Грешки с миграции
- Увери се, че всички миграции са налични
- Провери дали има проблеми с foreign keys в SQLite

### Грешки с автентикация
- Тестовете използват `Auth::login()` за автентикация
- Увери се, че middleware е правилно конфигуриран

## Покритие на тестове

Текущо покритие:
- **AiToolController**: ~90% (всички основни методи) ✅
- **CategoryController**: ~90% (всички основни методи) ✅
- **ToolReviewController**: ~90% (всички основни методи) ✅
- **AiToolPolicy**: 100% (всички методи) ✅
- **Form Requests**: ~80% (основни валидации) ✅

**Общо тестове:** 55+ тестови случая

## Добавяне на нови тестове

### Feature тест пример:

```php
/** @test */
public function it_can_do_something(): void
{
    // Arrange
    $user = User::factory()->create();
    Auth::login($user);

    // Act
    $response = $this->getJson('/api/endpoint');

    // Assert
    $response->assertStatus(200);
}
```

### Unit тест пример:

```php
/** @test */
public function it_validates_something(): void
{
    $request = new MyRequest();
    $rules = $request->rules();
    
    $validator = Validator::make([], $rules);
    
    $this->assertTrue($validator->fails());
}
```

## Best Practices

1. **Използвай factories** вместо директно създаване на модели
2. **Използвай RefreshDatabase** за чиста база данни
3. **Тествай edge cases** - гранични случаи
4. **Тествай авторизацията** - права и достъп
5. **Тествай валидацията** - всички валидационни правила
6. **Използвай descriptive имена** за тестовете
7. **Поддържай тестовете актуални** - обновявай ги при промени

---

**Последна актуализация:** Януари 2025

