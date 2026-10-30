# Проекты (multi-tenancy)

Один деплой сервиса обслуживает сколько угодно продуктов. Каждый продукт = «проект» со своей доской, своей командой и своими постами. Все проекты изолированы на уровне БД по `project_id`.

## Схема

```
                 feedback.rocont.ru / localhost:5176
                 ─────────────────────────────────────
                 один backend · одна БД · один widget.js
                                 │
         ┌───────────────────────┼───────────────────────┐
         ▼                       ▼                       ▼
    /demo доска              /kerr доска             /jlr доска
    posts.project_id=1       project_id=2            project_id=3
    categories+statuses      categories+statuses     categories+statuses
    (свои)                   (свои)                  (свои)
    admin@example.com        maria@kerr.local        ivan@rocont.ru
         ▲                       ▲                       ▲
         │ POST посты            │                       │
         │ widget.js             │                       │
         │ data-project="demo"   │ data-project="kerr"   │ data-project="jlr"
         │                       │                       │
    localhost:5175           localhost:1032          jlr.spb.ru
    (демо-сайт)              (сайт Kerr)             (сайт JLR)
```

Виджет на сайте продукта — всегда одна и та же строка, меняется только `data-project`:

```html
<script src="http://localhost:5176/widget.js" data-project="<slug>" defer></script>
```

Бэкенд при приёме поста проверяет две вещи:
1. `{project:slug}` в URL существует.
2. `Origin` HTTP-заголовка попадает в `projects.allowed_origins` этого проекта (middleware `AllowProjectOrigin`).

Это защищает от «я скопировал `data-project="kerr"` себе на сайт и буду флудить».

## Что сейчас заведено локально

Все пароли сейчас — `password` (локалка, тесты). В проде заводи через `admin:create` / `admin:password` — они генерят нормальный случайный пароль, показывают его один раз, и ты передаёшь его человеку.

| slug | Доска | Виджет разрешён на | Админ | Пароль |
|---|---|---|---|---|
| `demo` | http://localhost:5176/demo | `http://localhost:*`, `http://127.0.0.1:*` | `admin@example.com` | `password` |
| `kerr` | http://localhost:5176/kerr | `http://localhost:1032` | `maria@kerr.local` | `password` |

## Создать свой проект

```bash
docker compose exec backend php artisan project:create "Culture Map" \
    --slug=cmap \
    --origin=http://localhost:3000
```

- `--slug` — короткий идентификатор, латиница + дефисы. Используется и в URL доски (`/cmap`), и в `data-project` виджета.
- `--origin` — где виджет имеет право жить. Можно указать несколько раз: `--origin=http://localhost:3000 --origin=https://culturemap.ru`. Протокол + домен (+ порт), без пути. Для локалки подойдёт wildcard: `http://localhost:*`.

Команда напечатает готовую строку `<script>` для вставки на сайт продукта. Автоматически создаст категории «Баг»/«Идея» и три системных статуса (`Новое → Готово/Отклонено`).

## Создать админа

```bash
docker compose exec backend php artisan admin:create ivan@rocont.ru \
    --project=cmap \
    --name="Иван"
```

- `--project=slug` — к какому проекту прикрепить. Можно повторить: `--project=cmap --project=kerr` → один человек в двух досках.
- Пароль генерится и показывается **один раз** — скопируй сразу.
- Email должен быть уникальным. Чтобы сменить пароль существующему — `admin:password <email>`.

## Что делает админ, когда входит

- Открывает `http://localhost:5176/<slug>`, жмёт «Вход для команды», логинится.
- Видит **только посты своих проектов**. Если зайти на доску проекта, где его нет, — 404.
- Может редактировать статусы, категории, посты (менять статус, писать команде, удалять). См. роуты `/api/admin/*` в `backend/routes/api.php`.

## Проверить, что всё правильно подключено

```sql
-- посмотреть все проекты и их админов
SELECT p.id, p.slug, p.name, p.allowed_origins, string_agg(u.email, ', ') AS admins
FROM projects p
LEFT JOIN project_user pu ON pu.project_id = p.id
LEFT JOIN users u ON u.id = pu.user_id
GROUP BY p.id ORDER BY p.id;

-- сколько постов в каждом проекте
SELECT p.slug, count(po.id) AS posts
FROM projects p
LEFT JOIN posts po ON po.project_id = p.id
GROUP BY p.slug ORDER BY p.slug;
```

## Добавить ещё один origin к существующему проекту

Отдельной `artisan`-команды пока нет, проще всего SQL:

```sql
UPDATE projects
SET allowed_origins = allowed_origins || '["http://staging.myapp.com"]'::jsonb
WHERE slug = 'kerr';
```

(`||` в Postgres — конкатенация JSONB-массивов. Это как раз та фича, ради которой мы оставили Postgres.)

## Протестировать два проекта параллельно

Открой **две вкладки** и в DevTools Console каждой — свой инжект:

**Вкладка 1 — http://localhost:5175 (демо-сайт):**
```js
// там виджет и так уже вшит на demo, инжект не нужен
```

**Вкладка 2 — http://localhost:1032 (Kerr):**
```js
const s = document.createElement('script');
s.src = 'http://localhost:5176/widget.js';
s.dataset.project = 'kerr';
s.defer = true;
document.body.appendChild(s);
```

Отправь по посту в каждой. Теперь:
- http://localhost:5176/demo — только пост из `:5175`.
- http://localhost:5176/kerr — только пост из `:1032`.
- В PhpStorm: `SELECT project_id, title FROM posts;` — увидишь разные `project_id`.

Если попытаешься в вкладке Kerr вбить `data-project="demo"` — бэкенд ответит 403, потому что `localhost:1032` не в `allowed_origins` у `demo` (там только `localhost:*` → `localhost:1032` подходит, кстати, тоже подойдёт; попробуй наоборот — вбей `data-project="kerr"` на localhost:5175 → получишь 403, т.к. 5175 не в `kerr.allowed_origins`).

## Удалить проект

Прямой `artisan`-команды нет. Через SQL (каскадно подчистит посты, вложения, голоса, категории, статусы):

```sql
DELETE FROM projects WHERE slug = 'kerr';
```

Или если нужно начать совсем с чистого листа (пересоздать и БД, и демо-проект):
```bash
docker compose exec backend php artisan migrate:fresh --seed
```
