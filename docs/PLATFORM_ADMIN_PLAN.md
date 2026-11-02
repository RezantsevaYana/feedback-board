# Админка сервиса на Orchid — план

Ветка `adminka`. Решено 02.11.2026: админку сервиса делаем как в kerrdental.ru — на Orchid Platform, а не страницей во Vue.
Первая итерация — самая простая: вход, права, списки досок и пользователей (только просмотр, но с сортировкой, фильтрами и пагинацией).

**Как продолжать:** идти по чеклисту сверху вниз, первый неотмеченный пункт — следующий. После каждого пункта отмечать `[x]` и, если что-то решилось по ходу, дописывать в «Решения». Не коммитить — пользователь коммитит сам.

## Образец: kerrdental.ru (`../kerrdental.ru`)

- `orchid/platform` 14.53 на Laravel 13 (у нас Laravel 13.37 — совместимо).
- `app/Orchid/PlatformProvider.php` — меню (`registerMainMenu`), права (`registerPermissions`), хлебные крошки из enum.
- `app/Enums/OrchidRoutes.php` — enum модулей: `list()` / `create()` / `edit()` / `base()` → имена маршрутов `platform.<module>.list`…, `getTitle()` → заголовки и крошки.
- `routes/platform.php` — `Route::screen(...)` на каждый экран.
- `app/Orchid/Modules/<Сущность>/Screens/*ListScreen.php` — списки: наследуют `Abstractions/ListScreenPattern`, `query()` + `layout()` с `Layout::table('items', [TD::make(...)->sort()->filter()...])`, пагинация (`$paginate = 20`), фильтры-классы в `app/Orchid/Filters/`.
- `app/Orchid/Modules/User`, `Modules/Role`, `Layouts/User`, `Layouts/Role` — системные экраны пользователей и ролей (из стабов `orchid:install`).
- `app/Models/User.php` наследует `Orchid\Platform\Models\User`, у пользователя колонка `permissions` (json).
- Локаль `ru`, переводы Orchid — `lang/ru`.

## Решения

- **Адрес:** `/admin` на бэкенде (префикс Orchid по умолчанию). Наш API — `/api/admin/*`, не пересекается. Слаг `admin` уже в `feedback.reserved_slugs`.
- **Один origin, как у API:** в `frontend/vite.config.ts` проксировать на бэкенд `/admin` и `/vendor/orchid` (ассеты Orchid), чтобы админка открывалась на :5176 с той же сессионной кукой. На проде веб-сервер должен отдавать эти пути бэкенду (сейчас деплой сервиса не описан — отметить в README/DEPLOY).
- **Кто админ сервиса:** права Orchid (`permissions` у пользователя, роли), а не флаг `is_superadmin`. Доступ в админку — право `platform.index`. Пользователи те же, что входят в кабинет `/app`: одна таблица `users`, отдельной таблицы админов не делаем.
- **Конфликт таблиц:** у нас уже есть `attachments` (вложения постов). Миграцию Orchid `create_orchid_attachmentstable_table` **не публиковать / удалить** и не использовать поля Orchid, которые пишут в attachments (`Upload`, `Cropper`, `Picture`). Таблицы `roles` и `role_users` у нас свободны.
- **Локаль:** приложение на `en` (API-тексты у нас написаны по-русски вручную). Orchid показываем по-русски: локаль `ru` только для маршрутов админки — свой middleware в `config/platform.php` → `middleware.private` и `public`, а не смена `APP_LOCALE` для всего приложения.
- **Vue-версия из первого подхода убирается** (см. этап 0): страница `/app/platform` и API `/api/admin/platform/*` больше не нужны. В SPA остаётся только ссылка «Админка сервиса» в меню аккаунта → `/admin`.

## Чеклист

### Этап 0. Откатить Vue-версию (сделана 02.11.2026, не закоммичена)

- [ ] Откатить миграцию `is_superadmin` из локальной базы **до** удаления файла: `docker compose exec backend php artisan migrate:rollback --step=1` (проверить `migrate:status`, что откатывается именно `2026_11_02_000001_add_is_superadmin_to_users`).
- [ ] Удалить файлы:
  - `backend/database/migrations/2026_11_02_000001_add_is_superadmin_to_users.php`
  - `backend/app/Http/Controllers/Api/Admin/PlatformController.php`
  - `backend/tests/Feature/PlatformAdminTest.php` (переписывается в этапе 5)
  - `frontend/src/api/platform.ts`
  - `frontend/src/pages/platform/` (страница и две таблицы)
  - `frontend/src/scss/components/_platform-page.scss` и его `@use` в `scss/app.scss`
- [ ] Убрать правки:
  - `backend/routes/api.php` — группа `platform` в конце `auth:sanctum`
  - `backend/app/Providers/AppServiceProvider.php` — `Gate::define('platform.admin', ...)`
  - `backend/app/Models/User.php` — `$attributes` с `is_superadmin` и каст
  - `backend/database/seeders/DatabaseSeeder.php` — `forceFill(['is_superadmin' => true])` (заменится в этапе 2)
  - `frontend/src/router/index.ts` — маршрут `platform`, импорт `PlatformPage`, проверка `meta.superadmin` в `beforeEach`
- [ ] Оставить, но переделать в этапах ниже: `GrantSuperadmin.php` (этап 2), `is_superadmin` в `/me` (`AuthController::me`, тип `Me` во фронте) и пункт в `AccountMenu.vue` (этап 4), строки в `README.md` (этап 6).
- [ ] `php artisan test` и `npm run type-check` зелёные после отката.

### Этап 1. Поставить Orchid

- [ ] `docker compose exec backend composer require orchid/platform:^14.53` (версию сверить с kerr `composer.lock`).
- [ ] `php artisan orchid:install` — опубликует конфиг `config/platform.php`, миграции, `app/Orchid/PlatformProvider.php`, стабы экранов пользователей/ролей, `routes/platform.php`, ассеты в `public/vendor/orchid`.
- [ ] Проверить опубликованные миграции: удалить миграцию attachments (см. «Решения»); миграция users должна только **добавить** колонку `permissions` к существующей `users`, не создавать таблицу заново.
- [ ] `User` наследует `Orchid\Platform\Models\User` (как в kerr), сохранив наше: `MustVerifyEmail`, `#[Fillable]`/`#[Hidden]` (+ `permissions` в hidden), касты, `projects()`, `boards()`, `roleOn()`, `managesProject()`. Проверить, что `HasFactory`/`Notifiable` не задвоились.
- [ ] `php artisan migrate`, весь `php artisan test` зелёный (Orchid не должен сломать Sanctum, вход, `/me`).
- [ ] `.gitignore`: решить, коммитить ли `public/vendor/orchid` (в kerr — посмотреть, как сделано) или публиковать ассеты при деплое (`orchid:publish`).

### Этап 2. Доступ и права

- [ ] Middleware локали `ru` для админки (см. «Решения»), подключить в `config/platform.php`. Переводы Orchid на русский — проверить, что подтянулись (в kerr `lang/ru`).
- [ ] `config/platform.php`: `prefix` `/admin`, `index` → наш первый экран (список досок), бренд/название «Feedback Board — админка».
- [ ] `superadmin:grant {email} {--revoke}` переписать на права Orchid: выдать все права (`Dashboard::getAllowAllPermission()` или как делает `orchid:admin --id`), `--revoke` — очистить `permissions`. Альтернатива — штатный `orchid:admin`; в README описать один способ.
- [ ] Сиды: `admin@example.com` получает все права Orchid.
- [ ] `PlatformProvider::registerPermissions()` — группа «Сервис»: `platform.boards`, `platform.users` (по образцу kerr `platform.systems.*`).

### Этап 3. Экраны (первая итерация — только просмотр)

Структура как в kerr: `app/Orchid/Modules/<Сущность>/Screens/`, имена маршрутов через enum `app/Enums/OrchidRoutes.php` (`BOARD = 'board'`, `USER = 'user'`), крошки в `PlatformProvider`. Базовые классы `ListScreenPattern` / трейты kerr переносить только то, что нужно двум спискам (без сохранения/удаления — в первой итерации правки нет).

- [ ] `OrchidRoutes` enum с `list()` и `getTitle()`.
- [ ] **Доски** — `Modules/Board/Screens/BoardListScreen.php`, маршрут `platform.board.list`, право `platform.boards`:
  - колонки: название (ссылка на `/<slug>` в новой вкладке), slug, владелец (имя + почта; «из консоли» для `owner_id = null`), открытая/закрытая, опубликована ли (`isPublished()`), посты, участники, последний пост, создана, в корзине (`deleted_at`);
  - `Project::withTrashed()->with('owner')->withCount(['posts', 'members'])->withMax('posts as last_post_at', 'created_at')`;
  - сортировка по названию, созданию, постам; фильтр по названию/slug, видимости, «в корзине»; пагинация 20.
  - `Project` — подключить трейты `Filterable` / `AsSource` (и `$allowedSorts` / `$allowedFilters`), как у моделей kerr.
- [ ] **Пользователи** — системный экран Orchid `platform.systems.users` из стабов, дополнить колонками: почта подтверждена, доски с ролями (`projects` с pivot `role`), дата регистрации. Редактирование прав/ролей — штатное Orchid (это и есть управление админами сервиса). Удаление пользователя из стаба **убрать** в первой итерации (каскады по постам/доскам не продуманы).
- [ ] **Роли** — штатный экран `platform.systems.roles` из стабов, без изменений.
- [ ] Меню в `PlatformProvider::registerMainMenu()`: «Доски», «Пользователи», «Роли» (последние два — под заголовком «Права доступа», как в kerr).

### Этап 4. Связка с SPA

- [ ] `/me` → `is_superadmin` = `$user->hasAccess('platform.index')` (поле и тип `Me` уже есть).
- [ ] `AccountMenu.vue`: пункт «Админка сервиса» ведёт на `/admin` полной загрузкой страницы (`window.location`), а не `router.push`.
- [ ] `frontend/vite.config.ts`: прокси `/admin` и `/vendor/orchid` на `apiTarget`; перезапустить контейнер фронта.
- [ ] Проверить, что SPA-маршрут `/:slug` не перехватывает `/admin` (прокси Vite срабатывает раньше; на проде — правило веб-сервера).

### Этап 5. Тесты

- [ ] Переписать `tests/Feature/PlatformAdminTest.php`:
  - гость на `/admin/...` → редирект на вход Orchid;
  - пользователь без прав → 403;
  - с правами — экраны досок и пользователей открываются (200), в списке досок есть доска из корзины и легаси-доска без владельца;
  - `/api/admin/me` отдаёт `is_superadmin` true/false;
  - `superadmin:grant` выдаёт и `--revoke` забирает права, на неизвестной почте падает.
- [ ] Весь `php artisan test`, `vendor/bin/pint --dirty --format agent`, `npm run type-check`, `npm run lint`.

### Этап 6. Ручная проверка и документы

- [ ] В браузере: вход `admin@example.com` → меню аккаунта → «Админка сервиса» → `/admin` на :5176; доски (сортировка, фильтры, пагинация), пользователи, роли; пользователь без прав получает 403. **Не выходить из сессии пользователя в его браузере без спроса** — проверять в отдельном окне/профиле или спросить.
- [ ] `README.md`: раздел «Админка сервиса» — адрес, как выдать права, что на проде проксировать `/admin` и `/vendor/orchid`.
- [ ] `docs/BACKLOG.md`: следующие итерации (ниже).

## Следующие итерации (не сейчас)

- Карточка доски: правка владельца, блокировка, удаление/восстановление из корзины (с учётом `PurgeTrashedBoards`).
- Блокировка пользователя, сброс пароля письмом (вместо `admin:password`).
- Модерация постов по всем доскам.
- Дашборд с цифрами на `platform.main`.
- Журнал действий админов.
- Выгрузка в Excel (как `SiteUserExportModal` в kerr).

## Статус

_02.11.2026:_ план составлен. Vue-версия первой итерации (этап 0) сделана и лежит незакоммиченной в рабочей копии — её откатываем первым шагом. Локальному `admin@example.com` выдан `is_superadmin` командой и миграция применена — откат этапа 0 это убирает.