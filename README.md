# Feedback Board

Единое место для багов и предложений, вместо разрозненных сообщений по чатам и тредам.

## Идея

Сейчас баги и идеи пишут куда придётся: в чат разработки, в чат продукта, в треды, в общий чат. Они теряются, а автор не знает, что с его предложением.

Feedback Board решает это так:

- **Одна форма / виджет** — баг или предложение отправляется в одно место.
- **Статус и приоритет** — команда разбирает предложения и ведёт их по статусам.
- **Прозрачность для автора** — автор видит на доске, в каком статусе его предложение, и получает уведомление, когда оно выполнено.
- **Голосование** — за идеи можно голосовать, чтобы выделить самые нужные.

Проект самостоятельный и общий для любых продуктов: каждый продукт подключает виджет одним скриптом и получает свою доску.

## Запуск локально

Нужен только Docker.

```bash
docker compose up
```

Первый старт занимает пару минут: ставятся зависимости, создаётся база и демо-проект. Дальше:

| Адрес | Что это |
|---|---|
| http://localhost:5176/demo | Доска демо-проекта |
| http://localhost:5175 | «Чужой сайт» с подключённым виджетом |
| http://localhost:8000/api/… | API (на доске доступно через тот же адрес :5176) |
| http://localhost:8025 | Mailpit: все письма сервиса (подтверждение почты, сброс пароля) |

**Режим команды:** на доске нажмите «Войти» внизу бокового меню (ведёт на `/app/login` и обратно на доску) и войдите как `admin@example.com` / `password` (только для локальной разработки).

Демо-данные создаются один раз. Чтобы сбросить их:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

### Проекты и админы

```bash
docker compose exec backend php artisan project:create "My Product" --slug=my-product --origin=https://app.example.com
docker compose exec backend php artisan admin:create anna@example.com --project=my-product   # выведет пароль один раз
docker compose exec backend php artisan admin:password anna@example.com                     # новый пароль
docker compose exec backend php artisan superadmin:grant anna@example.com                   # админка сервиса (--revoke — забрать)
```

**Админка сервиса** — `/app/platform`, пункт «Админка сервиса» в меню аккаунта: все доски и все пользователи (пока только просмотр). Открывается только тем, кому выданы права командой `superadmin:grant`; локальный `admin@example.com` получает их из сидов.

Виджет на сайте продукта подключается одной строкой:

```html
<script src="https://<сервис>/widget.js" data-project="my-product" defer></script>
```

### Проверки

```bash
docker compose exec backend php artisan test        # тесты API
docker compose exec frontend npm run type-check     # типы фронтенда
docker compose exec frontend npm run lint
```

## Устройство

- `backend/` — Laravel 13 (PHP 8.5), REST API, Postgres, вход команды через Sanctum (сессия).
  API: `routes/api.php`, публичная часть — `/api/public/*`, режим команды — `/api/admin/*`.
- `frontend/` — Vue 3 + TypeScript + Vite: доска с режимом команды (отдельной админки нет).
  - `src/widget/` — виджет: собирается отдельно в один файл `public/widget.js`, рендерится в Shadow DOM.
  - `src/shared/components/SubmitForm.vue` — форма отправки, общая для виджета и доски.
  - Структура и правила фронтенда — в `frontend/CLAUDE.md`.
  - `demo/` — страница «чужого сайта» для проверки виджета.
- Docker Compose поднимает Postgres, бэкенд, фронтенд и демо-сайт.

## Документы

- `docs/TZ.md` — техническое задание на MVP
- `docs/BACKLOG.md` — идеи и отложенные решения (что обсудить, прежде чем брать в работу)
- `docs/prototype.html` — кликабельный прототип и схемы работы сервиса
- `frontend/demo/roles-prototype.html` и `roles-checklist.html` — флоу ролей и чеклист их ручной проверки (http://localhost:5175/roles-checklist.html)
