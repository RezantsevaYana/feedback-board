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
- **Отдельный guard `platform` для Orchid** (решено 03.11.2026 после первого прогона). SPA и Orchid сидят на одной таблице `users`, но каждая сторона держит свою сессионную подсистему: `web` для `/app/*` + `/api/admin/*`, `platform` для `/admin/*`. Отсюда: вход в кабинет не открывает `/admin` автоматически, и наоборот — разлогин в кабинете не выбрасывает из `/admin`. При переходе на `/admin` всегда показывается форма Orchid-логина, даже если ты вошла в SPA под обычным пользователем — это одна и та же cookie, но два независимых ключа внутри (`login_web_*` / `login_platform_*`). Без разделения получалась «двойная авторизация»: обычный юзер в SPA + Orchid middleware `platform` → 403 вместо редиректа на форму логина.
- **Конфликт таблиц:** у нас уже есть `attachments` (вложения постов). Миграцию Orchid `create_orchid_attachmentstable_table` **не публиковать / удалить** и не использовать поля Orchid, которые пишут в attachments (`Upload`, `Cropper`, `Picture`). Таблицы `roles` и `role_users` у нас свободны.
- **Локаль:** приложение на `en` (API-тексты у нас написаны по-русски вручную). Orchid показываем по-русски: локаль `ru` только для маршрутов админки — свой middleware в `config/platform.php` → `middleware.private` и `public`, а не смена `APP_LOCALE` для всего приложения.
- **Vue-версия из первого подхода убирается** (см. этап 0): страница `/app/platform` и API `/api/admin/platform/*` больше не нужны. В SPA остаётся только ссылка «Админка сервиса» в меню аккаунта → `/admin`.
- **Миграция `is_superadmin` на локальной базе не была применена** (на момент этапа 0 — Pending), откат миграции не потребовался — просто удалили файл. Если кто-то успел применить её у себя, перед удалением файла нужно `migrate:rollback --step=1`.

## Чеклист

### Этап 0. Откатить Vue-версию (сделана 02.11.2026, не закоммичена)

- [x] Откатить миграцию `is_superadmin` из локальной базы **до** удаления файла: `docker compose exec backend php artisan migrate:rollback --step=1` (проверить `migrate:status`, что откатывается именно `2026_11_02_000001_add_is_superadmin_to_users`). _На локальной базе миграция была Pending — откат не потребовался._
- [x] Удалить файлы:
  - `backend/database/migrations/2026_11_02_000001_add_is_superadmin_to_users.php`
  - `backend/app/Http/Controllers/Api/Admin/PlatformController.php`
  - `backend/tests/Feature/PlatformAdminTest.php` (переписывается в этапе 5)
  - `frontend/src/api/platform.ts`
  - `frontend/src/pages/platform/` (страница и две таблицы)
  - `frontend/src/scss/components/_platform-page.scss` и его `@use` в `scss/app.scss`
- [x] Убрать правки:
  - `backend/routes/api.php` — группа `platform` в конце `auth:sanctum`
  - `backend/app/Providers/AppServiceProvider.php` — `Gate::define('platform.admin', ...)`
  - `backend/app/Models/User.php` — `$attributes` с `is_superadmin` и каст
  - `backend/database/seeders/DatabaseSeeder.php` — `forceFill(['is_superadmin' => true])` (заменится в этапе 2)
  - `frontend/src/router/index.ts` — маршрут `platform`, импорт `PlatformPage`, проверка `meta.superadmin` в `beforeEach`
- [x] Оставить, но переделать в этапах ниже: `GrantSuperadmin.php` (этап 2), `is_superadmin` в `/me` (`AuthController::me`, тип `Me` во фронте) и пункт в `AccountMenu.vue` (этап 4), строки в `README.md` (этап 6).
- [x] `php artisan test` и `npm run type-check` зелёные после отката. _187 тестов зелёные, type-check чистый._

### Этап 1. Поставить Orchid

- [x] `docker compose exec backend composer require orchid/platform:^14.53` (версию сверить с kerr `composer.lock`). _У kerr `^14.43`, lock = 14.53.0 — ставим `^14.53`._
- [x] `php artisan orchid:install` — опубликует конфиг `config/platform.php`, миграции, `app/Orchid/PlatformProvider.php`, стабы экранов пользователей/ролей, `routes/platform.php`, ассеты в `public/vendor/orchid`. _Публикация миграций требует отдельный `vendor:publish --tag=orchid-migrations` (сам install только копирует конфиг/роуты/стабы и запускает migrate). После отдельной публикации удалили файл attachments. User.php был автоматически перезаписан — пересобрали его вручную в следующем пункте._
- [x] Проверить опубликованные миграции: удалить миграцию attachments (см. «Решения»); миграция users должна только **добавить** колонку `permissions` к существующей `users`, не создавать таблицу заново. _`create_orchid_users_table` действительно только добавляет `permissions jsonb nullable`. attachments-миграция удалена._
- [x] `User` наследует `Orchid\Platform\Models\User` (как в kerr), сохранив наше: `MustVerifyEmail`, `#[Fillable]`/`#[Hidden]` (+ `permissions` в hidden), касты, `projects()`, `boards()`, `roleOn()`, `managesProject()`. Проверить, что `HasFactory`/`Notifiable` не задвоились. _`HasFactory`/`Notifiable` приходят из `Orchid\Platform\Models\User` — в child их не повторяем. Добавили `allowedSorts`/`allowedFilters` под Orchid-таблицы._
- [x] `php artisan migrate`, весь `php artisan test` зелёный (Orchid не должен сломать Sanctum, вход, `/me`). _187 тестов зелёные._
- [x] `.gitignore`: решить, коммитить ли `public/vendor/orchid` (в kerr — посмотреть, как сделано) или публиковать ассеты при деплое (`orchid:publish`). _У kerr `public/vendor/orchid` коммитится (нет в `.gitignore`). У нас корневой `.gitignore` игнорит `vendor/` целиком — добавили `!backend/public/vendor/`, чтобы Orchid-ассеты попали в репо._

### Этап 2. Доступ и права

- [x] Middleware локали `ru` для админки (см. «Решения»), подключить в `config/platform.php`. Переводы Orchid на русский — проверить, что подтянулись (в kerr `lang/ru`). _`app/Http/Middleware/SetOrchidLocale.php` — `App::setLocale('ru')`; прописан в `config/platform.php` → `middleware.public` и `middleware.private`. Orchid-пакет из коробки грузит `ru.json` из `vendor/orchid/platform/resources/lang/ru.json`, отдельного `lang/ru` в проекте не нужно._
- [x] `config/platform.php`: `prefix` `/admin`, `index` → наш первый экран (список досок), бренд/название «Feedback Board — админка». _`prefix` `/admin` стоит по умолчанию. `index` временно оставлен `platform.main` — переключим на `platform.board.list` в этапе 3 вместе с созданием экрана. Бренд — Orchid рендерит `config('app.name')` в шапке и title; `APP_NAME="Feedback Board"` уже стоит, кастомного шаблона не делаем._
- [x] `superadmin:grant {email} {--revoke}` переписать на права Orchid: выдать все права (`Dashboard::getAllowAllPermission()` или как делает `orchid:admin --id`), `--revoke` — очистить `permissions`. Альтернатива — штатный `orchid:admin`; в README описать один способ. _`GrantSuperadmin.php` обновлён: grant кладёт `Dashboard::getAllowAllPermission()->all()` в `permissions`, revoke обнуляет. Для README решили: наш `superadmin:grant` остаётся основным способом._
- [x] Сиды: `admin@example.com` получает все права Orchid. _`DatabaseSeeder` сразу создаёт `admin@example.com` c `permissions => Dashboard::getAllowAllPermission()->all()`._
- [x] `PlatformProvider::registerPermissions()` — группа «Сервис»: `platform.boards`, `platform.users` (по образцу kerr `platform.systems.*`). _Метод называется `permissions()` в текущей версии Orchid. Группа «Сервис» с правами `platform.boards` / `platform.users`. Системная группа тоже оставлена, чтобы меню Пользователи/Роли управлялось через свои права._

### Этап 3. Экраны (первая итерация — только просмотр)

Структура как в kerr: `app/Orchid/Modules/<Сущность>/Screens/`, имена маршрутов через enum `app/Enums/OrchidRoutes.php` (`BOARD = 'board'`, `USER = 'user'`), крошки в `PlatformProvider`. Базовые классы `ListScreenPattern` / трейты kerr переносить только то, что нужно двум спискам (без сохранения/удаления — в первой итерации правки нет).

- [x] `OrchidRoutes` enum с `list()` и `getTitle()`. _Пока только `BOARD = 'board'`. `USER` не завели: пользователи используют штатный Orchid-роут `platform.systems.users`._
- [x] **Доски** — `Modules/Board/Screens/BoardListScreen.php`, маршрут `platform.board.list`, право `platform.boards`:
  - колонки: название (ссылка на `/<slug>` в новой вкладке), slug, владелец (имя + почта; «из консоли» для `owner_id = null`), открытая/закрытая, опубликована ли (`isPublished()`), посты, участники, последний пост, создана, в корзине (`deleted_at`);
  - `Project::withTrashed()->with('owner')->withCount(['posts', 'members'])->withMax('posts as last_post_at', 'created_at')`;
  - сортировка по названию, созданию, постам; фильтр по названию/slug, видимости, «в корзине»; пагинация 20.
  - `Project` — подключить трейты `Filterable` / `AsSource` (и `$allowedSorts` / `$allowedFilters`), как у моделей kerr.
  _Экран inline (без Layout-класса) с `Layout::table()`. Фильтр «в корзине» через стандартный Table-фильтр не завести — колонка `deleted_at` показывается без фильтра, доступ к записи с `deleted_at != null` через фильтр по дате; переделаем на отдельный URL-флаг во второй итерации. `index` в `config/platform.php` переключён на `platform.board.list` — `/admin` редиректит на список досок._
- [x] **Пользователи** — системный экран Orchid `platform.systems.users` из стабов, дополнить колонками: почта подтверждена, доски с ролями (`projects` с pivot `role`), дата регистрации. Редактирование прав/ролей — штатное Orchid (это и есть управление админами сервиса). Удаление пользователя из стаба **убрать** в первой итерации (каскады по постам/доскам не продуманы). _В `UserListLayout` добавлены колонки `email_verified_at`, `projects_count`, `created_at`; кнопка Delete убрана; метод `remove()` в `UserListScreen` снесён. «Доски с ролями» подробно (с перечислением досок и ролей) — отложено во 2-ю итерацию, пока только счётчик._
- [x] **Роли** — штатный экран `platform.systems.roles` из стабов, без изменений. _Оставлен as-is._
- [x] Меню в `PlatformProvider::registerMainMenu()`: «Доски», «Пользователи», «Роли» (последние два — под заголовком «Права доступа», как в kerr). _Текущая версия Orchid использует метод `menu()`, не `registerMainMenu()`. Группа «Сервис» (Доски) и «Access Controls» (Users/Roles). Дефолтные Example-экраны и их роуты удалены._

### Этап 4. Связка с SPA

- [x] `/me` → `is_superadmin` = `$user->hasAccess('platform.index')` (поле и тип `Me` уже есть). _`hasAccess()` приходит из Orchid-трейта `UserAccess` на родительском `User`._
- [x] `AccountMenu.vue`: пункт «Админка сервиса» ведёт на `/admin` полной загрузкой страницы (`window.location`), а не `router.push`. _Пункт `{ run: () => { window.location.href = '/admin' } }`. Убран `current: route.name === 'platform'` — в SPA такого роута больше нет._
- [x] `frontend/vite.config.ts`: прокси `/admin` и `/vendor/orchid` на `apiTarget`; перезапустить контейнер фронта. _Прокси добавлен, контейнер перезапущен. `curl -I http://localhost:5176/admin/login` → 200 с бэкенда._
- [x] Проверить, что SPA-маршрут `/:slug` не перехватывает `/admin` (прокси Vite срабатывает раньше; на проде — правило веб-сервера). _Vite dev-server проксирует до SPA-маршрутизации, запрос даже не доходит до роутера. На проде нужен nginx-правило — отметил в чеклисте этапа 6 (README)._

### Этап 5. Тесты

- [x] Переписать `tests/Feature/PlatformAdminTest.php`:
  - гость на `/admin/...` → редирект на вход Orchid;
  - пользователь без прав → 403;
  - с правами — экраны досок и пользователей открываются (200), в списке досок есть доска из корзины и легаси-доска без владельца;
  - `/api/admin/me` отдаёт `is_superadmin` true/false;
  - `superadmin:grant` выдаёт и `--revoke` забирает права, на неизвестной почте падает. _Все 6 кейсов зелёные. Нюанс: `UserAccess::hasAccess()` кэширует права на инстансе, `$user->refresh()` не сбрасывает приватные свойства — в тесте вместо `refresh()` используется `User::find($user->id)`._
- [x] Весь `php artisan test`, `vendor/bin/pint --dirty --format agent`, `npm run type-check`, `npm run lint`. _193 теста зелёные. Pint `--dirty` не работает изнутри контейнера (нет git) — прогнал по списку модифицированных файлов руками, зафиксировал стилистику (ordered imports, fully_qualified_strict_types). Type-check и lint зелёные._

### Этап 6. Ручная проверка и документы

- [ ] В браузере: вход `admin@example.com` → меню аккаунта → «Админка сервиса» → `/admin` на :5176; доски (сортировка, фильтры, пагинация), пользователи, роли; пользователь без прав получает 403. **Не выходить из сессии пользователя в его браузере без спроса** — проверять в отдельном окне/профиле или спросить. _На Yana — визуально пройтись по сценариям, Dev-прокси уже проверен curl'ом: `curl -I http://localhost:5176/admin/login` → 200 с бэкенда._
- [x] `README.md`: раздел «Админка сервиса» — адрес, как выдать права, что на проде проксировать `/admin` и `/vendor/orchid`. _Обновлён абзац «Админка сервиса» (`/admin`), добавлена заметка про прокси nginx на проде._
- [x] `docs/BACKLOG.md`: следующие итерации (ниже). _Добавлен раздел «Админка сервиса — следующие итерации» с карточкой доски, модерацией, журналом, дашбордом, экспортом._

## Следующие итерации (не сейчас)

- Карточка доски: правка владельца, блокировка, удаление/восстановление из корзины (с учётом `PurgeTrashedBoards`).
- Блокировка пользователя, сброс пароля письмом (вместо `admin:password`).
- Модерация постов по всем доскам.
- Дашборд с цифрами на `platform.main`.
- Журнал действий админов.
- Выгрузка в Excel (как `SiteUserExportModal` в kerr).

## Статус

_02.11.2026:_ план составлен. Vue-версия первой итерации (этап 0) сделана и лежит незакоммиченной в рабочей копии — её откатываем первым шагом. Локальному `admin@example.com` выдан `is_superadmin` командой и миграция применена — откат этапа 0 это убирает.

_03.11.2026:_ все этапы (0–5 и часть 6) выполнены, не закоммичено. Этапы 0 → 5 закрыты полностью; в этапе 6 остался только ручной прогон в браузере — ждёт Yana (сессию её пользователя не трогаю). 193 бэкенд-теста зелёные (включая новый `PlatformAdminTest` на 6 кейсов), фронт `type-check` и `lint` чистые. Прокси Vite на `/admin` и `/vendor/orchid` подтверждён curl'ом (200 с бэкенда). Pint отработал по модифицированным файлам.