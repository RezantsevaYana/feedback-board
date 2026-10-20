# Как тестить виджет

Три сценария — от быстрого к «как в бою».

## 1. Готовая демо-страница (быстрее всего)

В компоузе уже крутится «чужой сайт» с вшитым виджетом:

| | |
|---|---|
| Сайт с виджетом | http://localhost:5174 |
| Доска для просмотра | http://localhost:5173/demo |
| Таблица в PhpStorm | `posts` (см. [DATABASE.md](DATABASE.md)) |

Открыл **:5174** → справа внизу кнопка → отправил пост → на доске **:5173/demo** появится. В таблице `posts` в PhpStorm нажми Refresh (`Ctrl+F5`) — увидишь новую строку.

## 2. Виджет на твоём реальном локальном проекте (без правки его кода)

Подойдёт любой локальный проект на `http://localhost:*` или `http://127.0.0.1:*` — эти origin уже разрешены в `allowed_origins` проекта `demo`.

**Шаги:**

1. Открой свой проект в браузере (например, Kerr — http://localhost:1032).
2. `F12` → вкладка **Console**.
3. Вставь и `Enter`:

   ```js
   const s = document.createElement('script');
   s.src = 'http://localhost:5173/widget.js';
   s.dataset.project = 'demo';
   s.defer = true;
   document.body.appendChild(s);
   ```

4. Через секунду появится кнопка «Обратная связь» в правом нижнем углу. Отправь пост.
5. Посмотри результат на доске http://localhost:5173/demo или в `posts` в PhpStorm.

**Что это проверяет:**
- Виджет не наследует стили хост-сайта (Shadow DOM работает).
- Cross-origin POST реально ходит (origin `localhost:1032` ≠ бэкенд `localhost:8000`).
- Поведение поверх настоящей вёрстки, а не демо-странички.

**Нюанс:** при перезагрузке страницы инжект стирается — надо вставить заново. Если тестишь долго, см. п. 2a.

### 2a. Автоматический инжект через Tampermonkey (если тестишь часто)

Поставь расширение Tampermonkey → New script → вставь:

```js
// ==UserScript==
// @name         Feedback Board widget on Kerr
// @match        http://localhost:1032/*
// @run-at       document-end
// ==/UserScript==

const s = document.createElement('script');
s.src = 'http://localhost:5173/widget.js';
s.dataset.project = 'demo';
s.defer = true;
document.body.appendChild(s);
```

Теперь виджет сам поднимается на всех страницах Kerr без ручного вставления. Поменяй `@match` на свой проект, если это не Kerr.

## 3. Отдельная доска под твой проект (не мешать с `demo`)

Если хочешь, чтобы посты из реального приложения не смешивались с демо-данными, заведи ему свой проект:

```bash
docker compose exec backend php artisan project:create "Kerr" --slug=kerr --origin=http://localhost:1032
```

Доска будет на http://localhost:5173/kerr. В инжекте замени `s.dataset.project = 'demo'` → `'kerr'`.

Нужно добавить ещё один origin к существующему проекту (например, другой порт) — проще всего SQL-ом в PhpStorm:

```sql
UPDATE projects
SET allowed_origins = allowed_origins || '["http://localhost:3000"]'::jsonb
WHERE slug = 'demo';
```

## Если что-то не работает

- **Кнопка не появилась** — открой Console, посмотри ошибки. Если `[feedback-board] widget.js: add data-project=...` — забыла `s.dataset.project`.
- **Пост не отправляется, в Network 403/CORS** — origin не в белом списке. Проверь: `SELECT slug, allowed_origins FROM projects;` в PhpStorm.
- **Виджет странно выглядит** — пришли скриншот, посмотрю.
