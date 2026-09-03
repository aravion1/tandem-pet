# ID задачи: TASK-001

## Название задачи

Схема данных идентификации, ролей, участков и аудита.

## Исполнитель

DBA

## Полное описание

Подготовить PostgreSQL-миграции для платформенной модели данных и индексы. Схема должна поддерживать импорт жителей, связь владельцев с участками, гибкие роли и неизменяемый аудит.

## Контракты данных

`users(id UUID PK, phone_ciphertext TEXT, phone_hash CHAR(64) UNIQUE, password_hash TEXT NULL, consented_at TIMESTAMPTZ NULL, consent_version VARCHAR(64) NULL, activated_at TIMESTAMPTZ NULL, created_at, updated_at)`; `plots(id UUID PK, street VARCHAR(255), house VARCHAR(64))`; `user_plots(user_id, plot_id)` с составным PK; `roles`, `permissions(code UNIQUE)`, `role_permissions`, `user_roles`; `audit_logs(id UUID PK, actor_user_id NULL, action VARCHAR(100), entity_type VARCHAR(100), entity_id UUID NULL, payload JSONB, created_at)`. В `audit_logs` запрещены UPDATE и DELETE прикладной ролью БД.

## Эндпоинты

Не затрагиваются.

## Бизнес-логика

Номер ищется только по `phone_hash`; исходный номер хранится зашифрованно. Пароль до активации отсутствует. Ролевые связи многие-ко-многим. Предзаполнить подтверждённый каталог прав из ТЗ, включая `roles.manage`.

## Зависимости

Нет.

## Критерии готовности

Миграции применяются на чистой PostgreSQL БД и создают все таблицы, внешние ключи, уникальности и индексы; повторный запуск безопасен; есть проверка невозможности дублирующего номера и изменения аудита прикладной ролью.
