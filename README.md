# Levelion CRM

CRM-система для управления выездным ремонтом компьютерной техники и телевизоров.

## Технологии

- PHP 8.2
- Laravel
- MySQL 8.0
- Blade

## Установка (локальная разработка)

1. Клонировать репозиторий:
   ```bash
   git clone git@github.com:masterriadom-jpg/Levelion_dev.git
   cd Levelion_dev
   ```

2. Установить зависимости:
   ```bash
   composer install
   ```

3. Скопировать конфиг:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Настроить БД в `.env` и выполнить миграции:
   ```bash
   php artisan migrate --seed
   ```

5. Запустить сервер:
   ```bash
   php artisan serve
   ```

## Документация

- Карта проекта: `docs/PROJECT-MAP.md`
- План разработки: `docs/ПЛАН_РАЗРАБОТКИ.md`
- Глоссарий: `docs/GLOSSARY.md`
- История: `docs/ИСТОРИЯ_РАЗРАБОТКИ.md`

## Структура проекта

```
app/
├── Enums/        # Статусы и типы
├── Helpers/      # Вспомогательные функции
├── Http/         # Контроллеры и middleware
├── Models/       # Eloquent модели
└── Services/     # Бизнес-логика

config/
└── icons.php     # Конфигурация иконок

docs/             # Документация проекта
```

## Лицензия

Proprietary - все права защищены.
