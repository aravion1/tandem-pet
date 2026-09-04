# Резервное копирование и восстановление PostgreSQL

Секреты передаются только через локальный `.env`; не добавляйте его в Git. Перед восстановлением сохраните актуальный backup и убедитесь, что целевая БД не production.

## Backup

```sh
mkdir -p backups
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' > "backups/$(date +%Y%m%d-%H%M%S).dump"
```

Проверьте файл командой `pg_restore --list` внутри PostgreSQL-контейнера.

## Восстановление в чистую БД

```sh
docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" restore_check'
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d restore_check --clean --if-exists' < backups/backup.dump
docker compose exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" restore_check'
```

В production восстановление выполняется только по утверждённому окну работ и плану отката.

## Вложения

Volume `attachments_data` не входит в PostgreSQL dump. Его резервное копирование и восстановление выполняются отдельной процедурой на уровне Docker volume.

Текущая подтверждённая политика допускает файлы без лимита размера и квоты; исполняемые расширения блокируются. Свободное место volume должно контролироваться операционно, поскольку риск заполнения диска принят владельцем проекта.
