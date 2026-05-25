# Тестовое задание — Fullstack (Bitrix + Laravel)

Решение тестового задания на позицию Middle Fullstack-разработчика.

## Структура репозитория

```
├── laravel/                        # Часть 1 — REST API каталога
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── ProductController.php    # GET /api/products
│   │   │   │   └── OrderController.php      # POST /api/orders
│   │   │   └── Requests/
│   │   │       └── ProductIndexRequest.php  # Валидация параметров
│   │   ├── Models/
│   │   │   ├── Product.php
│   │   │   ├── Category.php
│   │   │   └── Stock.php
│   │   └── Services/
│   │       └── ProductService.php           # Бизнес-логика фильтрации
│   ├── database/
│   │   ├── migrations/                      # Структура таблиц с индексами
│   │   ├── seeders/
│   │   │   └── ProductSeeder.php            # Тестовые данные
│   │   └── factories/
│   │       └── ProductFactory.php
│   ├── routes/
│   │   └── api.php
│   └── tests/Feature/
│       └── ProductApiTest.php               # 6 тестов API
│
├── bitrix/                         # Часть 2 — Синхронизация заказов
│   └── local/
│       ├── php_interface/
│       │   ├── init.php                     # Точка входа
│       │   └── handlers/
│       │       ├── order_sync.php           # Обработчик OnSaleOrderSaved
│       │       └── order_sync_agent.php     # Агент повторной отправки
│       └── sql/
│           └── order_sync_queue.sql         # DDL таблицы очереди
│
└── docs/
    └── architecture.md             # Часть 3 — Ответы по архитектуре
```

## Часть 1. Laravel — REST API каталога

### Эндпоинт

```
GET /api/products
```

### Параметры запроса

| Параметр      | Тип     | Описание                          | По умолчанию |
|---------------|---------|-----------------------------------|--------------|
| `category_id` | integer | Фильтр по категории              | —            |
| `price_min`   | numeric | Минимальная цена                  | —            |
| `price_max`   | numeric | Максимальная цена                 | —            |
| `in_stock`    | boolean | Только в наличии (1) / нет (0)    | —            |
| `per_page`    | integer | Товаров на страницу (1–100)       | 20           |
| `sort_by`     | string  | Поле сортировки                   | id           |
| `sort_dir`    | string  | Направление (asc / desc)          | asc          |

### Пример запроса

```
GET /api/products?category_id=5&in_stock=1&price_min=100&price_max=5000&per_page=30&sort_by=price&sort_dir=asc
```

### Пример ответа

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "category_id": 5,
      "name": "Смартфон X100",
      "sku": "SKU-0000042",
      "price": "299.99",
      "in_stock": true,
      "category": {
        "id": 5,
        "name": "Электроника",
        "slug": "electronics"
      },
      "stocks": [
        { "id": 1, "warehouse": "main", "quantity": 150 }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 30,
    "has_more": true
  }
}
```

### Ключевые решения

**Почему Eloquent, а не Query Builder:**
Eloquent даёт удобную eager-загрузку связей через `with()`, что решает проблему N+1. Читаемость кода выше. На миллионе записей bottleneck — не overhead ORM (единицы миллисекунд), а отсутствие индексов.

**Почему `simplePaginate` вместо `paginate`:**
`paginate()` выполняет `SELECT COUNT(*)`, что на таблице в миллион строк стоит 200–500 мс. `simplePaginate()` делает `LIMIT N+1` и отдаёт только `has_more: true/false`.

**Индексы:**
Составной индекс `(category_id, in_stock, price)` покрывает основной сценарий фильтрации. Порядок колонок: equality-фильтры первыми, range-фильтр (`price`) последним — так MySQL использует B-tree максимально эффективно.

## Часть 2. Bitrix — Синхронизация заказов

### Как работает

1. При сохранении заказа срабатывает D7-событие `OnSaleOrderSaved`
2. Обработчик собирает данные заказа (ID, товары, сумма) и отправляет POST-запрос на Laravel API
3. HTTP-запрос обёрнут в try/catch с таймаутом 5 сек — при любой ошибке заказ сохраняется нормально
4. Если API недоступен — заказ записывается в таблицу `order_sync_queue`
5. Агент (запускается каждые 5 минут) подбирает неотправленные заказы и повторяет отправку
6. После 5 неудачных попыток запись помечается и ждёт ручного разбора

### Подключение

В файле `/local/php_interface/init.php`:
```php
require_once __DIR__ . '/handlers/order_sync.php';
```

### Фоновая отправка

Основной подход — **Bitrix-агент** (файл `order_sync_agent.php`). Альтернативы:
- `addBackgroundJob()` — выполняет задачу после отдачи ответа клиенту, но в рамках того же хита
- RabbitMQ/Redis Queue — для высоконагруженных проектов с сотнями заказов в минуту
- Отдельный cron-скрипт — более предсказуемое поведение, чем агенты на хитах

## Часть 3. Архитектура

Подробные ответы — в файле [`docs/architecture.md`](docs/architecture.md).

## Запуск тестов

```bash
cd laravel
php artisan test --filter=ProductApiTest
```

## Технологии

- PHP 8.1+
- Laravel 10+
- 1С-Битрикс D7
- MySQL 8.0 / MariaDB 10.6+
