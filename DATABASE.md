# Подключение к БД (локально)

Postgres 17 поднимается `docker compose up` в контейнере `feedback-board-db-1`. Все креды — в корневом `.env` (docker-compose подхватывает оттуда и в контейнер БД, и в backend). Для локальной разработки они не секретные.

## Доступы

| | Значение |
|---|---|
| Host | `localhost` |
| Port | `54321` *(проброс docker; внутри сети — `5432`)* |
| Database | `feedback_board` |
| User | `feedback_board` |
| Password | `secret` |
| JDBC URL | `jdbc:postgresql://localhost:54321/feedback_board` |

Если нужно поменять креды — редактируй корневой `.env` и пересоздавай контейнеры:

```bash
docker compose down -v     # -v дропнет volume, иначе Postgres не применит новые POSTGRES_*
docker compose up -d
```

## PhpStorm (Database tool)

1. `View → Tool Windows → Database` → `+` → `Data Source → PostgreSQL`.
2. Заполни значениями из таблицы выше. Если внизу просит скачать драйвер — скачай.
3. `Test Connection` → должно быть зелёное `Succeeded`.
4. `OK`. Слева появится дерево `feedback_board → public → posts, attachments, categories, projects, users, …`.

Двойной клик по таблице — просмотр и редактирование. `Ctrl+Shift+Q` — консоль SQL. Кнопка Refresh в тулбаре таблицы (`Ctrl+F5`) — обновить данные, чтобы увидеть свежие записи.

## CLI

**Внутри контейнера (ничего ставить не надо):**
```bash
docker exec -it feedback-board-db-1 psql -U feedback_board -d feedback_board
```

**С хоста (если установлен `psql`):**
```bash
PGPASSWORD=secret psql -h localhost -p 54321 -U feedback_board -d feedback_board
```

## Что в схеме

Основные таблицы:
- `projects` — проекты (`demo` создаётся сидером).
- `posts` — предложения с доски / виджета.
- `attachments` — скриншоты к постам.
- `categories` — «Баг», «Идея» и т.д., настраиваются админом проекта.
- `statuses` — статусы постов (новое, в работе, сделано и т.д.).
- `votes` — голоса за посты.
- `users` + `project_users` — админы команды (вход `admin@example.com` / `password`).

## Полезные запросы на старт

```sql
-- последние 10 постов
SELECT id, project_id, title, author_name, created_at
FROM posts ORDER BY created_at DESC LIMIT 10;

-- посты + число вложений
SELECT p.id, p.title, p.created_at, count(a.id) AS files
FROM posts p
LEFT JOIN attachments a ON a.post_id = p.id
GROUP BY p.id ORDER BY p.created_at DESC LIMIT 10;

-- голоса по постам
SELECT p.title, count(v.id) AS votes
FROM posts p
LEFT JOIN votes v ON v.post_id = p.id
GROUP BY p.id ORDER BY votes DESC LIMIT 10;

-- посмотреть, что приходит в реальном времени из psql: раз в 2 сек обновляет вывод
SELECT id, title, created_at FROM posts ORDER BY created_at DESC LIMIT 5
\watch 2
```

## Сбросить демо-данные

```bash
docker compose exec backend php artisan migrate:fresh --seed
```
