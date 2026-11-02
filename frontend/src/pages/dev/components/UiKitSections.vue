<template>
  <section class="ui-kit-section">
    <h2>Цвета</h2>
    <p class="ui-kit-where">Токены <code class="mono">base/_tokens.scss</code>, у каждого есть значение для тёмной темы. Цвет в компоненте — только через них.</p>
    <div class="ui-kit-swatches">
      <div v-for="t in TOKENS" :key="t.name" class="ui-kit-swatch">
        <span class="ui-kit-swatch-color" :class="`tone-${t.name}`" />
        <code class="mono">--{{ t.name }}</code>
        <span>{{ t.use }}</span>
      </div>
    </div>
  </section>

  <section class="ui-kit-section">
    <h2>Кнопки</h2>
    <table class="ui-kit-rules">
      <tbody>
        <tr><td><button type="button" class="btn btn-primary">Сохранить</button></td><td>Главное действие блока или окна — сохраняет, отправляет, создаёт. Одна на блок.</td><td>Сохранить, Отправить, Войти, Предложить, Одобрить, + Новая доска</td></tr>
        <tr><td><button type="button" class="btn btn-primary">Перейти на доску →</button></td><td>Переход на доску со страниц кабинета: тоже залитая зелёная, чтобы путь на доску не терялся.</td><td>Перейти на доску</td></tr>
        <tr><td><button type="button" class="btn">Отмена</button></td><td>Нейтральное: отмена, переход, копирование, второстепенное.</td><td>Отмена, Настройки, Доступ, Скопировать, Рассмотреть, Отправить ещё раз</td></tr>
        <tr><td><button type="button" class="btn btn-add">+ Добавить</button></td><td>Добавить элемент внутри блока.</td><td>+ Добавить домен, + Пригласить, + Добавить в команду, Добавить категорию / столбец</td></tr>
        <tr><td><button type="button" class="btn btn-danger">Отклонить</button></td><td>Отказ или удаление, которое ещё переспросит.</td><td>Отклонить, Удалить пост, Отменить приглашение, Удалить в панели выбора</td></tr>
        <tr><td><button type="button" class="btn btn-danger ui-kit-armed">Удалить</button></td><td>Финальное «да» удаления в окне подтверждения; и «Удалить доску».</td><td>«Удалить» в окне, Удалить доску</td></tr>
        <tr><td><button type="button" class="link-btn">Переименовать</button></td><td>Мелкое действие в строке.</td><td>Переименовать, Удалить в строке, Выбрать карточки, Вернуть стандартный</td></tr>
      </tbody>
    </table>
    <div class="ui-kit-row">
      <button type="button" class="btn btn-primary">Сохранить</button>
      <button type="button" class="btn">Отмена</button>
      <button type="button" class="btn btn-add">+ Добавить домен</button>
      <button type="button" class="btn btn-danger">Отклонить</button>
      <button type="button" class="btn btn-primary" disabled>Неактивна</button>
      <button type="button" class="btn btn-primary" aria-busy="true">Сохраняем…</button>
    </div>
    <div class="ui-kit-row">
      <button type="button" class="btn btn-sm">Компактная · .btn-sm</button>
      <button type="button" class="btn">Обычная · .btn</button>
      <button type="button" class="btn btn-lg btn-primary">Крупная · .btn-lg</button>
    </div>
    <div class="ui-kit-row">
      <CopyButton value="http://localhost:5176/demo" label="Скопировать ссылку" />
      <button type="button" class="link-btn">Ссылка-кнопка</button>
      <button type="button" class="icon-btn" aria-label="Закрыть"><AppIcon name="close" /></button>
    </div>
    <p class="ui-kit-where">Размеров три, и задаются они только в <code class="mono">_buttons.scss</code>: обычная — формы и шапки страниц (40px, на телефоне шрифт 14px); компактная — строки, карточки, списки («Настройки» / «Доступ» в карточке доски, «Отправить ещё раз» у приглашения); крупная — «Создать первую доску». Компонент задаёт кнопке только расположение, не размер.</p>
    <p class="ui-kit-where">Где: «Сохранить» в настройках, «+ Пригласить» и «+ Добавить в команду» на «Доступе», «Отклонить» в запросах, «Скопировать ссылку» в шапке страниц доски.</p>
  </section>

  <section class="ui-kit-section">
    <h2>Поля</h2>
    <p class="ui-kit-where">Компонент сам рисует подпись, подсказку «· …» и ошибку. Голые input / select не используем. На сенсорных экранах шрифт поля 16px — иначе iPhone увеличивает страницу.</p>
    <div class="ui-kit-grid">
      <InputComponent v-model="text" label="Название доски" hint="пользователи увидят его в шапке" maxlength="80" />
      <InputComponent v-model="bad" label="Почта" error="Это не похоже на почту." />
      <TextAreaComponent v-model="area" label="Описание" hint="по желанию" rows="3" />
      <SelectComponent v-model="role" label="Роль" :options="ROLE_OPTIONS" />
      <div class="ui-kit-stack">
        <span class="ui-kit-label">Поиск</span>
        <SearchComponent v-model="query" shortcut="/" />
      </div>
      <div class="ui-kit-stack">
        <CheckboxComponent v-model="check" label="Показывать скрытые" />
        <ColorPaletteComponent v-model="color" label="Цвет категории" variant="category" />
      </div>
    </div>
    <RadioCardsComponent v-model="mode" name="ui-kit-mode" :options="MODE_OPTIONS" />
    <p class="ui-kit-where">Где: формы настроек и входа, «Предложить», «Пригласить» (выбор роли), фильтры доски, «Кто видит доску» на «Доступе».</p>
  </section>

  <section class="ui-kit-section">
    <h2>Плашки и метки</h2>
    <div class="ui-kit-row">
      <CategoryTag :category="{ id: 1, name: 'Баг', color: 'red' }" />
      <CategoryTag :category="{ id: 2, name: 'Идея', color: 'blue' }" />
      <span class="pill" data-color="none">Новое</span>
      <span class="pill" data-color="green">Готово</span>
      <span class="pill" data-color="red">Отклонено</span>
      <span class="chip">базовый</span>
      <span class="count-badge">2</span>
    </div>
    <div class="ui-kit-row">
      <VisibilityBadge visibility="public" />
      <VisibilityBadge visibility="private" />
      <span class="role-chip owner">Владелец</span>
    </div>
    <p class="ui-kit-where">Где: категория и статус на карточке поста, «Открытая / Закрытая» в карточке доски и в сайдбаре доски (подсказка по наведению и тапу), счётчик запросов в меню, «Владелец» в списке участников.</p>
  </section>

  <section class="ui-kit-section">
    <h2>Голосование</h2>
    <div class="ui-kit-row">
      <span class="votes">
        <button type="button" class="vote" aria-pressed="true"><span class="emo" aria-hidden="true">👍</span><span>4</span></button>
        <button type="button" class="vote dislike"><span class="emo" aria-hidden="true">💩</span><span>1</span></button>
      </span>
      <span class="votes">
        <button type="button" class="vote"><span class="emo" aria-hidden="true">👍</span><span>0</span></button>
        <button type="button" class="vote dislike" aria-pressed="true"><span class="emo" aria-hidden="true">💩</span><span>2</span></button>
      </span>
      <span class="votes">
        <span class="voter-faces"><span class="voter-face">АП</span><span class="voter-face tone-1">ТМ</span><span class="voter-face tone-2">ИН</span><span class="voter-faces-rest">+2</span></span>
      </span>
    </div>
    <p class="ui-kit-where">Где: карточки в канбане, списке, «Топе», в карточке поста. Нажатая кнопка — свой голос. Аватарки — «кто голосовал» для команды на телефоне и планшете, на десктопе вместо них список при наведении.</p>
  </section>

  <section class="ui-kit-section">
    <h2>Меню, окна, уведомления</h2>
    <div class="ui-kit-row">
      <MenuComponent :items="MENU" class="ui-kit-menu" aria-label="Пример меню"><span>Меню</span><AppIcon name="chevron" /></MenuComponent>
      <button type="button" class="btn" @click="modal = true">Окно</button>
      <button type="button" class="btn" @click="askConfirm">Подтверждение</button>
      <button type="button" class="btn" @click="drawer = true">Выезжающая панель</button>
      <button type="button" class="btn" @click="notification.success('Сохранено')">Тост</button>
      <button type="button" class="btn" @click="notification.error('Не получилось сохранить')">Тост-ошибка</button>
      <ThemeToggle />
    </div>
    <p class="ui-kit-where">Окно — на телефоне нижняя шторка (карточка поста, «Предложить», «Кто голосовал»). Подтверждение — удаление, смена режима, выход из выбора. Панель — меню кабинета и доски на планшете и телефоне (на телефоне во весь экран). Тост — снизу, над закреплённой кнопкой, если она есть.</p>
  </section>

  <BaseModal v-if="modal" label="Пример окна" narrow @close="modal = false">
    <div class="drawer-top">
      <h2>Пример окна</h2>
    </div>
    <p class="note">Закрывается крестиком, Esc, кликом по фону, на телефоне — свайпом вниз.</p>
  </BaseModal>
  <NavDrawer v-if="drawer" label="Пример панели" @close="drawer = false">
    <nav class="app-sidebar-nav">
      <span class="nav-item" aria-current="page">Текущий пункт</span>
      <span class="nav-item">Другой пункт</span>
    </nav>
  </NavDrawer>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { PaletteColor } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import CategoryTag from '@/shared/components/CategoryTag.vue'
import VisibilityBadge from '@/shared/components/VisibilityBadge.vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import CheckboxComponent from '@/shared/ui/CheckboxComponent.vue'
import ColorPaletteComponent from '@/shared/ui/ColorPaletteComponent.vue'
import CopyButton from '@/shared/ui/CopyButton.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'
import NavDrawer from '@/shared/ui/NavDrawer.vue'
import RadioCardsComponent from '@/shared/ui/RadioCardsComponent.vue'
import SearchComponent from '@/shared/ui/SearchComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'

// The sections of /dev/ui: every shared piece with live state, and where it's used in the app.
const notification = useNotificationStore()
const confirm = useConfirmStore()

const TOKENS = [
  { name: 'accent', use: 'главное действие, выбор' },
  { name: 'accent-ink', use: 'ссылки, зелёный текст' },
  { name: 'active', use: 'выбранный пункт, свой голос' },
  { name: 'surface-2', use: 'подложки, ховер, плашки' },
  { name: 'line', use: 'рамки и разделители' },
  { name: 'fg', use: 'основной текст' },
  { name: 'muted', use: 'второстепенный текст' },
  { name: 'bug', use: 'ошибки, отказ, удаление' },
  { name: 'sta-orange', use: '«Закрытая», «не подтверждена»' },
]
const ROLE_OPTIONS = [
  { value: 'admin', label: 'Админ', hint: 'Всё, кроме удаления доски' },
  { value: 'manager', label: 'Менеджер', hint: 'Разбирает посты: статусы, ответы, скрытие' },
  { value: 'voter', label: 'Участник', hint: 'Читает, голосует и предлагает' },
]
const MODE_OPTIONS = [
  { value: 'public', label: 'Открытая', hint: 'Любой по ссылке читает, голосует и предлагает.' },
  { value: 'private', label: 'Закрытая', hint: 'Только участники. Остальные могут запросить доступ.' },
]
const MENU: MenuItem[] = [
  { label: 'Настройки аккаунта', run: () => undefined },
  { label: 'Мои доски', current: true, run: () => undefined },
  { label: 'Удалить', danger: true, separated: true, run: () => undefined },
]

const text = ref('Demo')
const bad = ref('bad-email')
const area = ref('')
const role = ref('voter')
const query = ref('')
const check = ref(true)
const color = ref<PaletteColor | null>('green')
const mode = ref('public')
const modal = ref(false)
const drawer = ref(false)

function askConfirm() {
  void confirm.ask({ title: 'Удалить категорию?', message: 'Посты останутся без категории.', confirmText: 'Удалить', danger: true })
}
</script>