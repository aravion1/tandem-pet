# TASK-000 — Bootstrap Laravel-приложения

## Оценка

2 часа.

## Область работ

Создать минимальный Laravel 13 каркас для PHP 8.5 с Docker, Composer, `artisan`, безопасным `.env.example` и PostgreSQL-конфигурацией.

## Ожидаемый результат

Репозиторий содержит запускаемый Laravel-каркас, совместимый с последующими Laravel-миграциями.

## Критерии приёмки

- Composer требует PHP 8.5 и Laravel `^13.0`;
- `docker compose run --rm app php artisan --version` выполняется;
- `.env.example` не содержит секретов;
- доступны команды миграций и тестов Laravel.

## Тест-кейсы

1. Проверить версии из `composer.json`.
2. Запустить `docker compose run --rm app php artisan --version`.
3. Запустить доступный базовый тест Laravel через Docker.
4. Проверить отсутствие секретов в `.env.example`.

## Контекст

- Основная спецификация: `SPECIFICATION.md`.
- Родительский план: `project/roadmap/ROADMAP.json`.
- Зависимости: отсутствуют.

## Статус выполнения

Выполнена. PHP 8.5 и Laravel запускаются через Docker; обязательные проверки пройдены.
