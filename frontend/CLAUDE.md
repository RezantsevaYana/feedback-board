# Frontend rules

Vue 3 + TypeScript + Vite + Pinia. The board (`src/`) and the embeddable widget (`src/widget/`, built separately into `public/widget.js`).

## Architecture (FSD, adapted)

```
src/
  api/            HTTP layer, one module per entity
    client.ts       request(), ApiError, errorMessage() — used only by api/* modules
    types.ts        response shapes
    posts.ts        PostsApi, projects.ts ProjectsApi, categories.ts CategoriesApi, auth.ts AuthApi
  shared/         used in more than one place, knows nothing about pages
    ui/             generic UI: modal, icon, inputs, checkboxes, buttons…
    components/     domain components shared by several pages / the widget (SubmitForm)
    composables/    shared composables (useEscapeKey)
    utils/          pure helpers (format, voter)
    constants/      texts and dictionaries (dict)
  pages/
    <page>/
      <Name>Page.vue
      components/   components of this page only
      composables/  composables of this page only
  store/          Pinia, split by module
    board/          useBoardStore.ts
    modals/         notifications and other UI-state stores (useNotificationStore.ts)
  widget/         widget entry (Shadow DOM)
  router/
  scss/
```

- **Where a new file goes:** used by one page → `pages/<page>/components|composables`. Used by two or more pages or by the widget → `shared/`. Generic, no domain logic (input, checkbox, modal) → `shared/ui/`.
- Imports go one way: `pages` → `shared` / `store` / `api`; `shared` never imports from `pages`. Between pages — nothing; move the common part to `shared/`.
- Absolute imports via `@/` for anything outside the current folder; `./` only within the same folder.
- **API layer** (as in kerrdental): every request goes through an `api/<entity>.ts` module — an object `XxxApi` with one method per endpoint, a base URL constant and a short comment above each method. Components and stores never build URLs or call `request()` directly.
  ```ts
  export const CategoriesApi = {
    // rename a category
    rename(id: number, name: string) {
      return request<Item<Category>>(`${BASE_URL}/categories/${id}`, { method: 'PATCH', body: { name } })
    },
  }
  ```
- Error text for the user: `errorMessage(e)` from `api/client`; `ApiError` only where status or field errors matter (404, 422).
- Notifications: `useNotificationStore()` → `.success(text)` / `.error(text)`.
- **Store files are named `useXxxStore.ts`**, one store per file, in a folder per module.
- The widget runs without Pinia and without the router: anything it imports (`shared/components/SubmitForm.vue` and below) must not use stores or the router.

## Form controls (`shared/ui`)

- Never write a raw `<input>`, `<textarea>`, `<select>` or checkbox in a page or shared component: use `InputComponent`, `TextAreaComponent`, `SelectComponent`, `CheckboxComponent` (naming as in kerrdental: `XxxComponent.vue`). A new kind of control (radio, file, date…) becomes a new `shared/ui/XxxComponent.vue` first.
- The component owns its label, hint and error: `label`, `hint` (shown as «· hint»), `error` props; `v-model` via `defineModel`. Don't wrap it in another `<label class="field">` and don't add a separate `.err` under it.
- Native attributes and listeners (`type`, `maxlength`, `placeholder`, `aria-label`, `autofocus`, `@input`…) are passed as usual and land on the native element (`inheritAttrs: false` + `v-bind="$attrs"`).
- `SelectComponent` takes `options: { value, label }[]`, built in `<script>` (not `<option>` markup in the template).
  ```vue
  <InputComponent v-model="form.name" label="Ваше имя" hint="по желанию" :error="errors.name" maxlength="80" />
  <SelectComponent v-model="edit.status" label="Статус" :options="statusOptions" />
  <CheckboxComponent v-model="view.showHidden" label="Скрытые" />
  ```

## Components

- Block order in a `.vue` file: `<template>`, then `<script setup lang="ts">`, then `<style>` (which we don't use, see below).
- `defineProps` / `defineEmits` are written as a multi-line object, one prop or event per line, never in one line:
  ```ts
  const props = defineProps<{
    project: string
    categories: Category[]
  }>()
  const emit = defineEmits<{
    close: []
    sent: [post: Post]
  }>()
  ```

## Styles

- **SCSS only, never in `.vue` files.** No `<style>` blocks in components (not even `scoped`) and no inline `style="..."`. Classes in templates, rules in `src/scss/`.
- Structure (same as in other company projects, e.g. kerrdental):
  - `scss/app.scss` — board entry, imported once in `main.ts`
  - `scss/widget.scss` — widget entry, imported `?inline` in `widget/main.ts` and put into the Shadow DOM via `defineCustomElement(..., { styles })`
  - `utils/` — `_variables.scss` (breakpoints), `_mixin.scss`; every partial starts with `@use '../utils' as *;`
  - `base/` — `_tokens.scss` (CSS custom properties), `_elements.scss` (reset, `.container`, page-level helpers)
  - `layout/` — page skeletons (`_board.scss`)
  - `components/` — one partial per component or shared UI piece, named after it (`CategoriesModal.vue` → `_categories-modal.scss`), with a comment naming the `.vue` file(s) it styles
  - `widget/` — widget-only styles; classes prefixed `fbw-`
- A new partial is added with `@use` to `app.scss`, and also to `widget.scss` if the widget renders it. The widget gets only what it needs.
- Modules: `@use` / `@forward`, never `@import`.
- **Colors** — only through CSS custom properties from `base/_tokens.scss` (`var(--accent)`), because of the dark theme and the widget's `:host`. A new color goes into tokens (light + dark), not as a hex in a component.
- **Breakpoints** — desktop-first, only through variables: `@media screen and (max-width: $mobile)`. Set: `$desktop` 1919, `$medium` 1439, `$tablet` 1023, `$mobile` 767, `$mobile-mini` 479. Media queries live inside the selector they change, after its base rules.
- **Container** — `.container` (max 1712px, side gutters 104/56/40/24/16px by breakpoint). A page is `<main class="container base-page">`; don't invent other wrappers with their own max-width/padding.
- Hover — `@include hover { ... }` (no hover on touch devices), not bare `&:hover`.
- **Class names: no BEM.** No `__` / `--` and no `&__` / `&--` built from parts. A class is a plain kebab-case name written out in full, starting with the component name: `.board-nav`, `.board-nav-item`, `.post-link`. States are separate short classes without an `is-` prefix: `.over`, `.dragging`, `.invalid`, `.narrow`.
- Nesting: rules for a component's inner parts and states go inside its selector (`&.over`, `&[aria-pressed='true']`, `img`, `.btn`). Keep it 2–3 levels deep.
- Formatting: 4-space indent, one declaration per line.