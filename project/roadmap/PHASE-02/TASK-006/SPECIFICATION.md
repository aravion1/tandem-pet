# ID задачи: TASK-006

## Название задачи

API обращений и задач.

## Исполнитель

Backend

## Полное описание

Реализовать CRUD, права просмотра, комментарии, вложения, назначения, сроки, приоритеты, время/бюджет и переходы статусов для единой сущности обращения/задачи.

## Контракты данных

Ответ `WorkItem` включает `id,kind,title,description,visibility,addressee,status,priority,due_at,assignees,attachments,comments,history,time_entries,budget_entries`. Для обращения статусы: `queued,in_progress,completed,cancelled,rejected`; для задачи: `queued,in_progress,suspended,review,completed,cancelled`. Значения сериализуются с русскими подписями на клиенте.

## Эндпоинты

`GET/POST /api/work-items`; `GET/PATCH /api/work-items/{id}`; `POST /api/work-items/{id}/comments`; `POST /api/work-items/{id}/attachments`; `POST /api/work-items/{id}/transition {status}`; `POST /api/work-items/{id}/convert {kind}`; `PUT /api/work-items/{id}/assignees`; `POST /api/work-items/{id}/time-entries`; `POST /api/work-items/{id}/budget-entries`. Чтение использует `requests.view_*` или `tasks.view`; изменение и статусы — `requests.moderate`/`tasks.manage`; назначение — `tasks.assign`; завершение задачи — `tasks.close`; бюджет — `budget.manage`. 403, 404, 409 (недопустимый переход), 422.

## Бизнес-логика

Житель отменяет только собственное обращение в `queued`. Публичное обращение видно по `requests.view_public`; личное — автору, адресату и `requests.view_private`. Завершение задачи разрешено только из `review` и только `tasks.close`. Любая смена значимых полей создаёт `work_history` и аудит. Вложения не редактируются и не заменяются.

## Зависимости

TASK-002: авторизация и права; TASK-004: файловое хранилище; TASK-005: схема.

## Критерии готовности

Feature-тесты покрывают все статусы, преобразование обоих типов, запрет отмены после работы, приватность и контроль каждого права.
