# Evolution CMS API

Полнофункциональный RESTful API для Evolution CMS, построенный на Laravel с использованием OpenAPI документации.

## 📋 Оглавление

- [Введение](#введение)
- [Установка](#установка)
- [Основные разделы API](#основные-разделы-api)
- [OpenAPI документация](#openapi-документация)

## 🚀 Введение

Evolution CMS API предоставляет полный набор RESTful эндпоинтов для управления всеми аспектами Evolution CMS, включая контент, пользователей, шаблоны, элементы и системные настройки.

### Ключевые особенности

- **Полное покрытие**: Все основные сущности Evolution CMS
- **RESTful дизайн**: Предсказуемая структура URL и методы HTTP
- **OpenAPI документация**: Автоматическая генерация документации
- **Пагинация и фильтрация**: Для всех списковых эндпоинтов
- **Валидация**: Встроенная валидация всех входящих данных
- **Логирование**: Подробное логирование всех операций

# Установка
Выполните команды из директории `/core`:
1. Установка пакета
```
php artisan package:installrequire roilafx/evolutionapi "*"
```


## Основные разделы API

### Контент (`/api/contents`)

#### Документы
```http
GET    /api/contents/documents              # Список документов
POST   /api/contents/documents              # Создать документ
GET    /api/contents/documents/{id}         # Получить документ
PUT    /api/contents/documents/{id}         # Обновить документ
DELETE /api/contents/documents/{id}         # Удалить документ
GET    /api/contents/documents/tree         # Дерево документов
PUT    /api/contents/documents/{id}/move    # Переместить документ
GET    /api/contents/documents/{id}/tv      # TV значения документа
```

#### Категории
```http
GET    /api/contents/categories             # Список категорий
POST   /api/contents/categories             # Создать категорию
GET    /api/contents/categories/{id}        # Получить категорию
PUT    /api/contents/categories/{id}        # Обновить категорию
DELETE /api/contents/categories/{id}        # Удалить категорию
```

#### Группы документов
```http
GET    /api/contents/document-groups        # Список групп
POST   /api/contents/document-groups        # Создать группу
GET    /api/contents/document-groups/{id}   # Получить группу
PUT    /api/contents/document-groups/{id}   # Обновить группу
DELETE /api/contents/document-groups/{id}   # Удалить группу
```

### Пользователи (`/api/users`)

#### Пользователи
```http
GET    /api/users/users                     # Список пользователей
POST   /api/users/users                     # Создать пользователя
GET    /api/users/users/{id}                # Получить пользователя
PUT    /api/users/users/{id}                # Обновить пользователя
DELETE /api/users/users/{id}                # Удалить пользователя
PUT    /api/users/users/{id}/block          # Заблокировать
PUT    /api/users/users/{id}/unblock        # Разблокировать
```

#### Роли
```http
GET    /api/users/roles                     # Список ролей
POST   /api/users/roles                     # Создать роль
GET    /api/users/roles/{id}                # Получить роль
PUT    /api/users/roles/{id}                # Обновить роль
DELETE /api/users/roles/{id}                # Удалить роль
```

#### Права доступа
```http
GET    /api/users/permissions               # Список прав
GET    /api/users/permissions/groups        # Список групп прав
POST   /api/users/permissions/groups        # Создать группу прав
```

#### Группы пользователей
```http
GET    /api/users/member-groups             # Список групп
POST   /api/users/member-groups             # Создать группу
GET    /api/users/member-groups/{id}        # Получить группу
PUT    /api/users/member-groups/{id}        # Обновить группу
DELETE /api/users/member-groups/{id}        # Удалить группу
```

### Шаблоны и TV (`/api/templates`)

#### Шаблоны
```http
GET    /api/templates/templates             # Список шаблонов
POST   /api/templates/templates             # Создать шаблон
GET    /api/templates/templates/{id}        # Получить шаблон
PUT    /api/templates/templates/{id}        # Обновить шаблон
DELETE /api/templates/templates/{id}        # Удалить шаблон
```

#### TV переменные
```http
GET    /api/templates/tvs                   # Список TV
POST   /api/templates/tvs                   # Создать TV
GET    /api/templates/tvs/{id}              # Получить TV
PUT    /api/templates/tvs/{id}              # Обновить TV
DELETE /api/templates/tvs/{id}              # Удалить TV
```

### Элементы (`/api/elements`)

#### Чанки
```http
GET    /api/elements/chunks                 # Список чанков
POST   /api/elements/chunks                 # Создать чанк
GET    /api/elements/chunks/{id}            # Получить чанк
PUT    /api/elements/chunks/{id}            # Обновить чанк
DELETE /api/elements/chunks/{id}            # Удалить чанк
```

#### Сниппеты
```http
GET    /api/elements/snippets               # Список сниппетов
POST   /api/elements/snippets               # Создать сниппет
GET    /api/elements/snippets/{id}          # Получить сниппет
PUT    /api/elements/snippets/{id}          # Обновить сниппет
DELETE /api/elements/snippets/{id}          # Удалить сниппет
```

#### Плагины
```http
GET    /api/elements/plugins                # Список плагинов
POST   /api/elements/plugins                # Создать плагин
GET    /api/elements/plugins/{id}           # Получить плагин
PUT    /api/elements/plugins/{id}           # Обновить плагин
DELETE /api/elements/plugins/{id}           # Удалить плагин
```

#### Модули
```http
GET    /api/elements/modules                # Список модулей
POST   /api/elements/modules                # Создать модуль
GET    /api/elements/modules/{id}           # Получить модуль
PUT    /api/elements/modules/{id}           # Обновить модуль
DELETE /api/elements/modules/{id}           # Удалить модуль
```

### Система (`/api/systems`)

#### Логи
```http
GET    /api/systems/logs/event-logs         # Логи событий
GET    /api/systems/logs/manager-logs       # Логи менеджера
DELETE /api/systems/logs/event-logs/clear   # Очистить логи событий
```

#### Настройки
```http
GET    /api/systems/settings                # Список настроек
POST   /api/systems/settings                # Создать настройку
GET    /api/systems/settings/{name}         # Получить настройку
PUT    /api/systems/settings/{name}         # Обновить настройку
DELETE /api/systems/settings/{name}         # Удалить настройку
```

## 📖 OpenAPI документация

API полностью документирован с использованием PHP атрибутов OpenAPI. Документация генерируется автоматически.


### Формат документации

```php
#[OA\Tag(name: 'Documents', description: 'Управление документами')]
#[OA\Get(
    path: '/api/contents/documents',
    summary: 'Список документов',
    description: 'Получить список документов с пагинацией',
    tags: ['Documents'],
    parameters: [...],
    responses: [
        new OA\Response(response: 200, ref: '#/components/responses/200'),
        new OA\Response(response: 422, ref: '#/components/responses/422'),
    ]
)]
```

### Автогенерация

Swagger автоматически генерирует примеры запросов и ответов на основе реальных данных контроллеров.