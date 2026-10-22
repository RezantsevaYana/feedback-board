# Self-serve админка досок — план имплементации

Фича: сейчас проект и админа заводят через `php artisan project:create` / `admin:create`. Делаем self-serve: админ может сам зарегаться, создать доску, настроить её, получить скрипт виджета и прямую ссылку, переключаться между своими досками.

Документ рассчитан на то, что работа продолжается в разных сессиях. Каждая итерация — отгружаемый кусок ценности. В конце — чеклист прогресса, отмечай пройденное.

---

## 1. Продуктовые решения (не пересматриваем)

- **URL-схема:** админка под `/app/*`, публичные доски на корне `/<slug>` (как сейчас). Зарезервированные slug'и: `app`, `api`, `widget.js`, `admin`, `register`, `login`, `about`, `privacy`, `terms` (список расширяется).
- **Регистрация без подтверждения email в MVP.** Защита: капча (reCAPTCHA v3 или hCaptcha) + rate limit на регистрацию. Количество досок на аккаунт **не ограничиваем** (ранее был лимит 3 на бесплатном тарифе — убрано, тарифов пока нет).
- **Домены (`allowed_origins`)** админ добавляет сам в настройках. При создании доски поле опциональное — без домена доска работает только по прямой ссылке.
- **Один владелец на доску в MVP.** Pivot `project_user` остаётся many-to-many, но UI команды нет. На `projects` добавляем `owner_id` для быстрой выборки "мои доски".
- **Soft delete** доски с корзиной на 30 дней. Крон удаляет физически через 30 дней.
- **Slug read-only** после создания (ломает уже подключённые виджеты и ссылки). Явно пишем это в UI.
- **Системные статусы** (`Новое`, `Готово`, `Отклонено`) можно переименовывать, но нельзя удалять.
- **Главная `/`** остаётся заглушкой, но под текущим текстом добавляются CTA "Создать доску" + "Войти". Полноценный лендинг — вне MVP.
- **Два равнозначных способа подключения:** виджет (`<script>`) и прямая ссылка на доску. Оба показываются одинаково крупно, никакой "или-или".

## 2. UX-карта экранов

| URL | Экран | Детали |
|---|---|---|
| `/` | Home + CTA | Текущая заглушка + блок "Создать доску / Войти" |
| `/app/register` | Регистрация | email, password, name. Капча (итерация 5). Без ограничения на количество досок |
| `/app/login` | Логин | Standalone-страница. `LoginModal.vue` на доске остаётся как короткий путь для команды — его оставляем |
| `/app/boards` | Hub | Карточки досок с метриками (N в очереди, посл. активность). Кнопка "+ Новая". Корзина внизу (если есть) |
| `/app/boards/new` | Мастер создания | Одна форма: название, slug (авто-транслит), домены (опционально). Live-проверка slug |
| `/app/boards/:slug/settings` | Настройки | Левое меню: Общее / Категории / Статусы / Домены / Как подключить / Удалить |
| `/app/boards/:slug/embed` | Как подключить | Скрипт + прямая ссылка. Одна страница, два входа: сразу после создания и из меню настроек |
| `/<slug>` | Публичная доска | Без изменений. В футере — "Powered by Feedback Board — создать свою" |

На каждой админской странице — шапка: лого (кликабельно → hub), название текущей доски с переключателем (если контекст доски), справа email + меню "выход".

## 3. Что уже готово (не трогаем, переиспользуем)

Backend:
- Таблицы `users`, `projects`, `project_user`, `categories`, `statuses`, `posts` — изоляция по `project_id`
- Миграции до `2026_10_22_000002_add_color_to_statuses` включительно
- `App\Models\Project`: `admins()`, `allowsOrigin()`, `createDefaultCategories()`, `createDefaultStatuses()`
- Admin API: `/api/admin/login`, `/me`, `/logout`, `/projects/{slug}/posts`, CRUD категорий и статусов, bulk-операции
- Artisan: `project:create`, `admin:create`, `admin:password` — оставляем, они нужны для внутренних досок без UI

Frontend:
- `src/pages/board/BoardPage.vue` + все компоненты модалок
- `src/pages/board/components/LoginModal.vue` — для логина с самой доски (не трогаем)
- `src/pages/board/components/CategoriesModal.vue`, `StatusesModal.vue` — переиспользуем в настройках, превратив в views без обёртки модалки
- `src/api/auth.ts`, `categories.ts`, `statuses.ts`, `projects.ts` — расширяем, не ломаем
- `src/widget/` — не трогаем вообще

## 4. Что добавляем на бэке

### Миграции
1. `add_owner_id_to_projects` — `owner_id` nullable foreign key на `users`. Для существующих проектов (`demo`, `kerr`) оставляем `null` — они "legacy", не в чьём-то кабинете.
2. `add_soft_deletes_to_projects` — `deleted_at timestamp nullable`.

### Модели
- `App\Models\Project`: добавить `use SoftDeletes`, relation `owner()` → `BelongsTo<User>`.
- `App\Models\User`: добавить relation `boards()` → `HasMany<Project>` (через `owner_id`). Старый `projects()` (many-to-many через pivot) оставить — его использует существующий код в `AuthController::me()` и в `BoardPolicy` (если есть).

### Сервисный слой
Выделяем `App\Services\ProjectService` с методом `create(string $name, string $slug, array $origins, ?User $owner): Project`. Внутри: валидация slug (включая reserved list), `Project::create`, `createDefaultCategories`, `createDefaultStatuses`. Используется:
- `CreateProject` artisan-командой (переписать на вызов сервиса)
- Новым `BoardController@store`

### Контроллеры (новые)
- `App\Http\Controllers\Api\Admin\RegistrationController` — `store(RegisterRequest)`: валидация email/password/капчи, создание юзера, автологин через Auth::login + session регенерация, возврат `me`.
- `App\Http\Controllers\Api\Admin\BoardController`:
  - `index` — список досок текущего юзера (owner_id = user.id, с метриками: `queue_count` где status.slug=`new`, `last_post_at`).
  - `store(CreateBoardRequest)` — через ProjectService. Без лимита на количество досок.
  - `update(UpdateBoardRequest)` — пока только `name` и `allowed_origins`.
  - `destroy` — soft delete, с подтверждением slug в теле запроса.
  - `restore` — восстановление из корзины.
  - `embed(slug)` — возвращает `{ script_tag, direct_url, widget_domain }` с domain из конфига.
  - `checkSlug(slug)` — live-проверка свободности и формата.

### Form Requests
- `RegisterRequest` — email unique, password min 8, name max 80, captcha token.
- `CreateBoardRequest` — name max 80, slug alpha_dash+ascii+not_in reserved+unique, origins array optional (валидация формата).
- `UpdateBoardRequest` — то же, но slug не меняется.

### Роуты (`routes/api.php`)
```php
Route::prefix('admin')->group(function () {
    Route::post('register', [RegistrationController::class, 'store'])->middleware('throttle:register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('boards', [BoardController::class, 'index']);
        Route::post('boards', [BoardController::class, 'store']);
        Route::post('boards/check-slug', [BoardController::class, 'checkSlug']);
        Route::get('boards/{slug}/embed', [BoardController::class, 'embed']);
        Route::patch('boards/{slug}', [BoardController::class, 'update']);
        Route::delete('boards/{slug}', [BoardController::class, 'destroy']);
        Route::post('boards/{slug}/restore', [BoardController::class, 'restore']);
    });
});
```

### Политики
- `BoardPolicy`: `view`, `update`, `delete`, `restore` — только `owner_id === auth()->id()`. Применить `authorize` в контроллере.
- Существующие политики (постов, категорий, статусов) если работают через `project_user` — не ломаем, добавляем проверку по owner_id в дополнение.

### Конфиг
- Новый `config/feedback.php`:
  ```php
  return [
      'public_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),
      'reserved_slugs' => ['app', 'api', 'admin', ...],
      'trash_retention_days' => (int) env('FEEDBACK_TRASH_RETENTION_DAYS', 30),
  ];
  ```
  Виджет-URL = `public_url . '/widget.js'`, вычисляется в контроллере, отдельного env не нужно.
- `.env.example` обновить.

### Throttling (`bootstrap/app.php` или `RouteServiceProvider`)
- `throttle:register` — 5 запросов / час / IP.
- Для `login` throttle уже есть.

### Крон
- `app/Console/Commands/PurgeTrashedBoards.php` — `Project::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->forceDelete()`.
- Регистрация в `routes/console.php`: `Schedule::command(...)->daily();`

## 5. Что добавляем на фронте

### Структура папок
```
src/
  pages/
    home/                HomePage.vue (меняем — добавляем CTA)
    board/               (не трогаем)
    auth/
      RegisterPage.vue
      LoginPage.vue
      components/
    app/
      BoardsHubPage.vue
      BoardNewPage.vue
      BoardSettingsPage.vue
      BoardEmbedPage.vue
      components/
        BoardCard.vue
        TrashSection.vue
        SettingsNav.vue
        SettingsGeneral.vue
        SettingsCategories.vue   (переиспользует логику CategoriesModal)
        SettingsStatuses.vue     (переиспользует StatusesModal)
        SettingsDomains.vue
        DeleteBoardSection.vue
  shared/
    ui/
      CopyButton.vue             (новый)
      ConfirmModal.vue           (если нет)
    components/
      AppHeader.vue              (шапка админки — лого + email + меню)
      BoardSwitcher.vue          (выпадашка со всеми досками, используется на доске и в hub)
    utils/
      slugify.ts                 (транслит кириллицы + slug normalize)
  api/
    boards.ts                    (BoardsApi)
    auth.ts                      (добавить register)
  store/
    auth/
      useAuthStore.ts            (currentUser, boards, login/register/logout)
```

### Роутер (`src/router/index.ts`)
```ts
routes: [
  { path: '/', name: 'home', component: HomePage },
  { path: '/app/register', name: 'register', component: RegisterPage, meta: { guest: true } },
  { path: '/app/login',    name: 'login',    component: LoginPage, meta: { guest: true } },
  { path: '/app/boards',   name: 'hub',      component: BoardsHubPage, meta: { auth: true } },
  { path: '/app/boards/new', name: 'board-new', component: BoardNewPage, meta: { auth: true } },
  { path: '/app/boards/:slug/settings', name: 'board-settings', component: BoardSettingsPage, meta: { auth: true } },
  { path: '/app/boards/:slug/embed',    name: 'board-embed',    component: BoardEmbedPage,    meta: { auth: true } },
  { path: '/:slug', name: 'board', component: BoardPage },
  { path: '/:slug/posts/:id(\\d+)', name: 'post', component: BoardPage },
],
```
Навигационный guard: `meta.auth` → проверить `useAuthStore().me`, иначе redirect `/app/login?next=...`. `meta.guest` → если залогинен, redirect на `/app/boards`.

### API-модули
`src/api/boards.ts`:
```ts
export const BoardsApi = {
  list()                { return request<Collection<Board>>('/admin/boards') },
  create(body)          { return request<Item<Board>>('/admin/boards', { method:'POST', body }) },
  update(slug, body)    { return request<Item<Board>>(`/admin/boards/${slug}`, { method:'PATCH', body }) },
  destroy(slug, confirm){ return request<void>(`/admin/boards/${slug}`, { method:'DELETE', body:{ confirm } }) },
  restore(slug)         { return request<Item<Board>>(`/admin/boards/${slug}/restore`, { method:'POST' }) },
  embed(slug)           { return request<Item<EmbedInfo>>(`/admin/boards/${slug}/embed`) },
  checkSlug(slug)       { return request<Item<{ available: boolean, reason?: string }>>('/admin/boards/check-slug', { method:'POST', body:{ slug } }) },
}
```
`src/api/auth.ts` — дополнить:
```ts
register(body) { return request<Item<Me>>('/admin/register', { method:'POST', body }) }
```

### Стор
`src/store/auth/useAuthStore.ts`:
- state: `me: Me | null`, `boards: Board[]`, `loading: boolean`
- actions: `fetchMe`, `login`, `register`, `logout`, `fetchBoards`, `addBoard`, `removeBoard`
- Инициализация: `fetchMe` при старте приложения (в `App.vue`), чтобы знать state до рендера guard'ов.

### Главная (минимальная правка)
`src/pages/home/HomePage.vue` — под текущим текстом добавить блок с двумя кнопками: "Создать доску" → `/app/register`, "Войти" → `/app/login`.

### SCSS
По правилам в `frontend/CLAUDE.md` — только `src/scss/`, никаких `<style>` в `.vue`. Новые партиалы:
- `scss/layout/_app-shell.scss` — layout админки (шапка + контент)
- `scss/components/_board-card.scss`
- `scss/components/_boards-hub.scss`
- `scss/components/_board-new.scss`
- `scss/components/_board-settings.scss`
- `scss/components/_board-embed.scss`
- `scss/components/_auth-pages.scss`
- `scss/components/_copy-button.scss`

Каждый добавить `@use` в `scss/app.scss`. Виджет (`widget.scss`) не трогаем — новых стилей там нет.

## 6. Итерации

Каждая итерация — отгружаемая. Можно останавливаться после любой и получать рабочий инкремент.

### Итерация 1 — Регистрация, логин, пустой hub
**Цель:** новый юзер может зарегаться, войти и попасть в пустой hub.

Backend:
- Миграция `add_owner_id_to_projects`
- Миграция `add_soft_deletes_to_projects`
- Модель Project: SoftDeletes + owner()
- Модель User: boards()
- RegistrationController + RegisterRequest + route
- BoardController@index (пустой возврат ок)
- throttle:register middleware
- Обновление `AuthController@me` — добавить `boards` в ответ (альтернатива — отдельный `/boards` эндпоинт)

Frontend:
- `useAuthStore` базовый (login, register, logout, fetchMe)
- RegisterPage + LoginPage (standalone)
- Пустая BoardsHubPage — состояние "0 досок" с кнопкой "Создать первую"
- Главная: добавить CTA
- Роутер + guard'ы
- AppHeader компонент
- Шеф-повар проверочной прогонки:
  - `/app/register` → заполнил → редирект на `/app/boards`
  - `/app/login` → заполнил → `/app/boards`
  - прямой заход `/app/boards` без логина → `/app/login`
  - кликнул "Выйти" → `/app/login`

### Итерация 2 — Создание доски и просмотр списка
**Цель:** админ создаёт доску через UI и видит её на hub с метриками.

Backend:
- `ProjectService::create(name, slug, origins, owner)` — вынести логику из CreateProject
- Переписать CreateProject на использование сервиса
- `BoardController@store` + CreateBoardRequest (с проверкой reserved slugs; без лимита на количество досок)
- `BoardController@checkSlug` для live-проверки
- `BoardController@embed` — возвращает script tag + direct url (с доменом из config/feedback.php)
- Доработать `BoardController@index` — метрики (queue_count, last_post_at)
- `config/feedback.php` + .env.example

Frontend:
- `utils/slugify.ts` — транслит кириллицы
- `api/boards.ts`
- Пополнить `useAuthStore.boards`
- BoardNewPage — форма с живой проверкой slug (debounce 400ms)
- BoardCard компонент
- BoardsHubPage — рендер карточек + пустое состояние
- CopyButton shared/ui
- BoardEmbedPage — рендер скрипта и ссылки
- Редирект после создания: `/app/boards/new` → `/app/boards/:slug/embed`
- Проверочная прогонка:
  - 0 → 1 доска: регистрация → "Создать первую" → форма → embed → "Открыть доску"
  - Создание 2-й и 3-й (и 4-й, и 10-й — ограничений нет)
  - Slug `demo` занят → "попробуйте: demo-2"
  - Slug `api` зарезервирован → ошибка
  - На карточке видно "3 в очереди" после отправки поста через виджет

### Итерация 3 — Настройки доски
**Цель:** админ переименовывает доску, управляет категориями/статусами/доменами из `/app/boards/:slug/settings`.

Backend:
- `BoardController@update` + UpdateBoardRequest
- Проверить BoardPolicy — update только owner
- Эндпоинты категорий/статусов/origins уже есть — проверить, что admin:sanctum middleware и policy пускают только owner'а

Frontend:
- BoardSettingsPage со SettingsNav
- SettingsGeneral — rename, slug read-only
- SettingsCategories — переписать `CategoriesModal.vue` как SettingsCategories (та же логика без обёртки модалки). Либо выделить общий composable.
- SettingsStatuses — аналогично (с запретом удаления системных)
- SettingsDomains — список origins + add/remove, валидация формата
- Якорная навигация (`#categories`, `#statuses` и т.д.)
- Проверочная прогонка:
  - Переименование доски — видно на карточке в hub
  - Добавление/удаление категории работает так же, как раньше в модалке на доске
  - Нельзя удалить "Новое"
  - Добавление домена → виджет на этом домене начинает работать

### Итерация 4 — Удаление и переключение между досками
**Цель:** можно удалить доску (с восстановлением) и быстро переключаться между своими досками из шапки.

Backend:
- `BoardController@destroy` — проверка `confirm === slug` в теле
- `BoardController@restore`
- Доработка `index` — отдельный ключ для `trashed_boards`
- `PurgeTrashedBoards` command + расписание
- Политика restore только owner

Frontend:
- DeleteBoardSection — модалка подтверждения с инпутом slug
- TrashSection на hub
- BoardSwitcher — выпадашка в шапке доски и hub (список досок + "+ Новая" + "Выйти")
- Внедрить BoardSwitcher в BoardPage (заменить/дополнить текущую шапку)
- Проверочная прогонка:
  - Удаление с правильным slug — редирект на hub, тост
  - Удаление с неправильным slug — кнопка не активируется
  - Восстановление из корзины
  - Переключение между 3 досками через выпадашку

### Итерация 5 — Защита от шума
**Цель:** регистрация защищена от ботов, зарезервированные slug'и enforced.

Backend:
- Интеграция reCAPTCHA v3 (или hCaptcha) на RegistrationController
- throttle:register уже заложен в итерации 1 — проверить
- `.env` для секретов капчи

Frontend:
- Виджет капчи на RegisterPage (загрузка скрипта провайдера)
- Отображение rate-limit ошибки нормально

Также:
- Футер на `/<slug>` с ссылкой "Powered by Feedback Board — создать свою" → `/app/register`

**Лимит досок на аккаунт в MVP НЕ ставим.** Если появятся тарифы — добавить сюда отдельным пунктом.

## 7. Тесты

Минимум на бэке — тесты, которые дают уверенность в многотенантности и правах:
- `tests/Feature/Admin/RegistrationTest` — happy path, уникальность email, валидация
- `tests/Feature/Admin/BoardTest`:
  - create → owner_id корректный
  - index возвращает только мои доски
  - update чужой доски → 403
  - delete требует подтверждения slug
  - restore возвращает из корзины
  - reserved slug → 422
- `tests/Feature/ProjectServiceTest` — создание проекта создаёт категории/статусы по умолчанию

Фронт — type-check + lint обязательно. Юнит-тесты — опционально, по месту.

## 8. Миграция существующих данных

Проекты `demo` и `kerr` остаются без `owner_id` (null). Это ок — они "системные", недоступны через UI админа. Если нужно сделать их видимыми для `admin@example.com`:
```sql
UPDATE projects SET owner_id = (SELECT id FROM users WHERE email = 'admin@example.com') WHERE slug IN ('demo', 'kerr');
```
Это разовая операция, не часть миграции (чтобы в проде не затронуть реальные данные).

Старая связь `project_user` остаётся — её использует существующий флоу авторизации на доске. Новая связь `owner_id` — дополнение, не замена.

## 9. Открытые микро-вопросы

Эти всплывут в процессе — реши в коде по месту, зафиксируй, если изменится принципиально:

- **Автологин после регистрации?** Да, иначе юзер должен тут же логиниться вручную.
- **"Выход" в шапке — куда редирект?** На `/app/login`.
- **"Открыть доску" из embed — в новой вкладке или в той же?** В новой — чтобы embed-страница осталась открытой для копирования.
- **Переименование категории с цветом — палитра из 5-6 токенов?** Да, см. `base/_tokens.scss` — взять оттуда 6 заготовленных accent-цветов, не произвольный HEX.
- **Валидация формата origin:** `regex:#^https?://[^/\s]+$#` (как в CreateProject). Поддержка wildcard порта `http://localhost:*` — да.
- **Last activity на карточке:** брать `MAX(posts.created_at)` или `UPDATED_AT`? Для MVP — `created_at` самого свежего поста. "4 часа назад", "вчера", "неделю назад".

## 10. Чеклист прогресса

Отмечай по мере выполнения.

### Итерация 1 — Регистрация и hub
- [x] Миграция `add_owner_id_to_projects` — `2026_10_23_000001_add_owner_id_to_projects.php`
- [x] Миграция `add_soft_deletes_to_projects` — `2026_10_23_000002_add_soft_deletes_to_projects.php`
- [x] Project model: SoftDeletes + owner()
- [x] User model: boards() (добавлен HasMany, projects() оставлен для legacy)
- [x] RegistrationController + RegisterRequest (`app/Http/Requests/Admin/RegisterRequest.php`, `app/Http/Controllers/Api/Admin/RegistrationController.php`)
- [x] Route POST /api/admin/register + throttle:register (RateLimiter в AppServiceProvider: 5/час/IP)
- [x] BoardController@index (базовый) — пока queue_count=0, last_post_at=null; метрики в итерации 2
- [x] AuthController@me расширен — добавлен ключ `boards`
- [x] frontend useAuthStore (`src/store/auth/useAuthStore.ts`) — me, loaded, fetchMe, login, register, logout
- [x] Me type + BoardSummary + AuthApi.register
- [x] RegisterPage + LoginPage (`src/pages/auth/*`) — @vuelidate + server errors как в LoginModal
- [x] BoardsHubPage + BoardNewPage placeholder (`src/pages/app/*`)
- [x] HomePage CTA — две кнопки "Создать доску" / "Войти"
- [x] Роутер + guard'ы (meta.auth / meta.guest, fetchMe lazy)
- [x] AppHeader (`src/shared/components/AppHeader.vue`)
- [x] SCSS партиалы: `_app-shell`, `_auth-pages`, `_boards-hub`, `_home-page` + подключены в `app.scss`

**Проверено curl'ом:** регистрация с CSRF → 200 + autologin, boards пустые; `/api/admin/boards` без сессии → 401. Миграции применились. Dev-сервер отдаёт все новые роуты (200), HMR подхватил изменения.

**Что проверить руками в браузере (http://localhost:5173):**
- `/` → под заглушкой кнопки "Создать доску" + "Войти"
- `/app/register` → заполнил → редирект на `/app/boards` → empty state "Привет, {name}"
- `/app/login` после logout → заполнил → `/app/boards`
- Прямой `/app/boards` без логина → редирект на `/app/login?next=/app/boards`
- Прямой `/app/login` залогиненным → редирект на `/app/boards`
- Клик "Выход" в шапке → редирект на `/app/login`
- `/app/boards/new` → страница-заглушка "скоро"

### Итерация 2 — Создание и список
- [x] ProjectService (`app/Services/ProjectService.php`) — транзакция + default categories/statuses
- [x] CreateProject переписан на сервис (`admin:create` НЕ трогал, он не создаёт проекты)
- [x] BoardController@store + CreateBoardRequest (reserved slugs через `Rule::notIn`; **лимит досок убрали — делаем без ограничений**)
- [x] BoardController@checkSlug — `{available, reason: format|reserved|taken|null}`
- [x] BoardController@embed — `{script_tag, widget_url, direct_url, allowed_origins}`; 403 для чужой доски
- [x] BoardController@index с метриками (`queue_count` через withCount, `last_post_at` через withMax)
- [x] config/feedback.php + .env.example (public_url из FRONTEND_URL, reserved_slugs, FEEDBACK_TRASH_RETENTION_DAYS)
- [x] slugify.ts (`src/shared/utils/slugify.ts`) — ru→en map + normalize
- [x] api/boards.ts — BoardsApi (list, create, embed, checkSlug) + Board / EmbedInfo / SlugCheck types
- [x] Collection<T> type в types.ts
- [x] useAuthStore расширен: `boards: Board[]` (с метриками, отдельно от `me.boards` summaries), `fetchBoards`, `addBoard`, `boardsLoaded/boardsLoading`
- [x] CopyButton (`src/shared/ui/CopyButton.vue`) + иконка `copy` в AppIcon
- [x] BoardCard (`src/pages/app/components/BoardCard.vue`) — queue_count с акцентом если >0, относительная последняя активность (fmtTimeAgo из format.ts)
- [x] BoardsHubPage — реальный список с fetchBoards на mount, empty state сохранён
- [x] BoardNewPage — мастер с автогенерацией slug из имени (slugify + stop-sync после ручного редактирования), live checkSlug с debounce 400ms, origins массив (add/remove), server-side errors
- [x] BoardEmbedPage — welcome-блок если ?created=1, два равнозначных способа (скрипт + ссылка), warn-блок если origins пусты
- [x] Роут `/app/boards/:slug/embed` + редирект create → embed с `?created=1`
- [x] SCSS: `_board-card`, `_board-new`, `_board-embed`, `_copy-button` + подключены в app.scss
- [x] fmtTimeAgo добавлен в `src/shared/utils/format.ts`

**Проверено:** dev-сервер отдаёт все 3 новых роута (`/app/boards`, `/app/boards/new`, `/app/boards/:slug/embed`) — 200. HMR подхватил без ошибок.

**Что проверить руками в браузере:**
- Регистрация нового аккаунта → редирект на hub (empty state) → "Создать первую доску"
- Введи имя "Культурная карта" → slug автоматически "kulturnaya-karta"
- Поправь slug вручную → автосинк перестаёт работать
- Введи `demo` в slug → "Адрес занят. Попробуйте добавить цифру."
- Введи `api` в slug → "Этот адрес зарезервирован."
- Добавь 1-2 домена через "+ Добавить домен"
- Сабмит → редирект на embed с welcome-блоком, кнопка "Готово, вернуться в hub"
- Копируй скрипт и ссылку, проверь "Скопировано ✓" на 2 сек
- Открой доску по кнопке "Открыть доску ↗" — должна работать
- Вернись в hub → карточка с именем, slug, "Очередь пуста", "постов ещё нет"
- Создай вторую доску без доменов → warn-блок на embed-странице "Домены ещё не добавлены"
- Открой чужую доску `/demo` (без owner) — должна открываться, но ты там не админ

### Итерация 3 — Настройки
- [ ] BoardController@update + UpdateBoardRequest
- [ ] BoardSettingsPage + SettingsNav
- [ ] SettingsGeneral
- [ ] SettingsCategories
- [ ] SettingsStatuses
- [ ] SettingsDomains

### Итерация 4 — Удаление и переключение
- [ ] BoardController@destroy + restore
- [ ] PurgeTrashedBoards command + schedule
- [ ] DeleteBoardSection
- [ ] TrashSection
- [ ] BoardSwitcher
- [ ] Интеграция BoardSwitcher в BoardPage

### Итерация 5 — Защита
- [ ] Капча на регистрации (бэк + фронт)
- [ ] Reserved slugs enforcement (бэк) — уже сделано в итерации 2, перепроверить
- [ ] Футер на `/<slug>` со ссылкой "Создать свою"

### Тесты
- [ ] RegistrationTest
- [ ] BoardTest
- [ ] ProjectServiceTest

---

## Как возобновить работу

1. `git pull`, открой этот файл.
2. Найди в чеклисте первый непомеченный пункт.
3. Открой итерацию, в которой он лежит — там прописано, что делать и как проверить.
4. Если застрял на решении, которое не описано — зафиксируй его в раздел "Открытые микро-вопросы" и продолжай.
