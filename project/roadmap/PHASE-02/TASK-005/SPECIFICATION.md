# ID задачи: TASK-005

## Название задачи

Схема данных обращений, задач, обсуждений и публикаций.

## Исполнитель

DBA

## Полное описание

Создать миграции предметной области коммуникаций и работ с полной историей изменений, вложениями, временем и бюджетом.

## Контракты данных

`work_items(id UUID PK, kind ENUM(request,task), title, description, visibility ENUM(public,private) NULL, addressee_user_id NULL, status VARCHAR(32), priority VARCHAR(32), due_at TIMESTAMPTZ NULL, created_by, created_at, updated_at)`; `work_assignees(work_item_id,user_id NULL, external_name VARCHAR(255) NULL)` с правилом «ровно одно поле исполнителя заполнено»; `work_comments`, `work_attachments`, `work_history(id,work_item_id,actor_user_id,field,old_value JSONB,new_value JSONB,created_at)`, `time_entries`, `budget_entries`. `discussions`, `discussion_members`, `messages`, `message_likes`, `discussion_verdicts`; `news`, `infoboards`, `attachments`; `notifications(id UUID PK,user_id,event_type,payload JSONB,created_at,delivered_at NULL)`. Добавить индексы по статусу, автору, адресату, сроку, участникам и получателю уведомления.

## Эндпоинты

Не затрагиваются.

## Бизнес-логика

`kind` меняется между request/task, история не удаляется. Личная видимость требует адресата. В закрытом обсуждении новые сообщения запрещены, переписка сохраняется. Один актуальный вердикт на обсуждение.

## Зависимости

TASK-001: пользователи и аудит.

## Критерии готовности

Миграции создают связи, ограничения и индексы; тесты БД подтверждают историю при смене типа, приватность адресата, исполнителя из пользователя или введённого вручную и единственный вердикт.
