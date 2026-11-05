<template>
  <div class="app-sidebar" :class="{ drawer }">
    <nav class="app-sidebar-nav" aria-label="Кабинет">
      <RouterLink to="/app/boards" class="nav-item" :aria-current="atHub ? 'page' : undefined">Мои доски</RouterLink>
    </nav>

    <!-- every board the user configures; the open one shows its two pages, another is a click away -->
    <section v-if="managed.length" class="app-sidebar-boards" aria-label="Доски">
      <div v-for="b in visible" :key="b.slug" class="app-sidebar-board" :class="{ cur: b.slug === currentSlug, open: b.slug === shownSlug }">
        <!-- a link to the same page of another board; the board on screen is just its name;
             in the panel every row is a button that unfolds its pages in the menu itself -->
        <component :is="rowTag(b)" v-bind="rowAttrs(b)" class="app-sidebar-board-row">
          <span class="app-sidebar-board-mark" aria-hidden="true">{{ boardMark(b.name) }}</span>
          <!-- the open board says who I am on it: owner and admin see the same pages, so it's the only hint -->
          <span class="app-sidebar-board-title">
            <span class="app-sidebar-board-name">{{ b.name }}</span>
            <span v-if="b.slug === shownSlug && b.role" class="app-sidebar-board-role">{{ ROLE_LABELS[b.role] }}</span>
          </span>
          <span class="app-sidebar-board-lock" :class="{ public: b.visibility === 'public' }" :title="b.visibility === 'private' ? 'Закрытая доска' : 'Открытая доска'">
            <AppIcon :name="b.visibility === 'private' ? 'lock' : 'unlock'" :size="13" />
            <span class="visually-hidden">{{ b.visibility === 'private' ? 'закрытая' : 'открытая' }}</span>
          </span>
          <!-- hidden while the pages are open: «Доступ» carries the number then -->
          <span v-if="b.pending_requests" class="count-badge" :aria-label="`запросов на доступ: ${b.pending_requests}`">{{ b.pending_requests }}</span>
        </component>

        <div class="app-sidebar-board-pages" :inert="b.slug !== shownSlug">
          <nav class="app-sidebar-nav" :aria-label="`Страницы доски ${b.name}`">
            <RouterLink :to="`/app/boards/${b.slug}/settings`" class="nav-item" :aria-current="b.slug === currentSlug && onSettings ? 'page' : undefined">Настройки</RouterLink>
            <!-- the open page's sections fold out under it; both lists stay in the DOM so the switch animates -->
            <div v-if="!drawer && b.slug === currentSlug" class="app-sidebar-sub" :class="{ open: onSettings }" :inert="!onSettings">
              <SectionLinks :path="`/app/boards/${b.slug}/settings`" :sections="SETTINGS_SECTIONS" :active="onSettings" />
            </div>
            <RouterLink :to="`/app/boards/${b.slug}/access`" class="nav-item" :aria-current="b.slug === currentSlug && onAccess ? 'page' : undefined">
              Доступ<span v-if="b.pending_requests" class="count-badge" :aria-label="`запросов на доступ: ${b.pending_requests}`">{{ b.pending_requests }}</span>
            </RouterLink>
            <div v-if="!drawer && b.slug === currentSlug" class="app-sidebar-sub" :class="{ open: onAccess }" :inert="!onAccess">
              <SectionLinks :key="b.visibility" :path="`/app/boards/${b.slug}/access`" :sections="accessSections(b)" :active="onAccess" />
            </div>
          </nav>
        </div>
      </div>
      <button v-if="hiddenCount" type="button" class="app-sidebar-more" @click="expanded = true">Ещё {{ hiddenCount }}</button>
      <button v-else-if="expanded" type="button" class="app-sidebar-more" @click="expanded = false">Свернуть</button>
    </section>

    <!-- the account sits in the same menu as the boards, not in a place of its own; one short page, no sections -->
    <section class="app-sidebar-group" aria-label="Аккаунт">
      <nav class="app-sidebar-nav" aria-label="Аккаунт">
        <RouterLink to="/app/account" class="nav-item" :aria-current="onAccount ? 'page' : undefined">Настройки аккаунта</RouterLink>
      </nav>
    </section>

    <!-- always at the bottom, on every cabinet page: the way out and the theme, one under the other -->
    <div class="app-sidebar-foot">
      <button type="button" class="sidebar-link sidebar-link-out" :disabled="busy" :aria-busy="busy" @click="logout">
        {{ busy ? 'Выходим…' : 'Выйти' }}
      </button>
      <ThemeToggle />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ROLE_LABELS, type BoardSummary } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useLogout } from '@/shared/composables/useLogout'
import { canConfigure } from '@/shared/utils/roles'
import { boardMark } from '@/shared/utils/format'
import SectionLinks, { type SectionLink } from '@/shared/components/SectionLinks.vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'

// The cabinet's side menu (AppShell), under the header: «Мои доски», every board the user configures — the open one
// with its two pages («Настройки» — what the board is, «Доступ» — who sees it and how they get in) and their
// sections, the others a row each, a click opening the same page of that board — then the account,
// «Выйти» and the theme at the bottom. Boards where the user only takes part stay on «Мои доски» and in the header's
// switcher (prototype: frontend/demo/sidebar-boards-prototype.html).
// `drawer` — the same menu in the tablet / phone panel (NavDrawer): a tap on a board unfolds its pages right there,
// so another board's «Доступ» is two taps away, and the pages' sections are left out to keep the panel short.
const props = defineProps<{
  drawer?: boolean
}>()
const auth = useAuthStore()
const route = useRoute()
const { logout, busy } = useLogout()

const SETTINGS_SECTIONS: SectionLink[] = [
  { hash: '#general', label: 'Общее' },
  { hash: '#categories', label: 'Категории' },
  { hash: '#statuses', label: 'Статусы' },
]

// up to this many rows, the current board always among them; one more than that is shown rather than «Ещё 1»
const VISIBLE = 6

const atHub = computed(() => route.name === 'hub' || route.name === 'board-new')
const onSettings = computed(() => route.name === 'board-settings')
const onAccess = computed(() => route.name === 'board-access')
const onAccount = computed(() => route.name === 'account')
const currentSlug = computed(() => (route.params.slug ? String(route.params.slug) : null))

// every board the user is on and may configure (an admin of someone else's board too); what waits for a decision
// first, as on «Мои доски», then by name
const managed = computed(() =>
  (auth.me?.projects ?? [])
    .filter((b) => canConfigure(b.role))
    .sort((a, b) => (b.pending_requests ?? 0) - (a.pending_requests ?? 0) || a.name.localeCompare(b.name, 'ru')),
)
const expanded = ref(false)
const visible = computed(() => {
  const all = managed.value
  if (expanded.value || all.length <= VISIBLE + 1) return all
  const head = all.slice(0, VISIBLE)
  const cur = all.find((b) => b.slug === currentSlug.value)
  return cur && !head.includes(cur) ? [...head.slice(0, VISIBLE - 1), cur] : head
})
const hiddenCount = computed(() => managed.value.length - visible.value.length)

// the board whose pages are unfolded: the one on screen, or in the panel the one tapped last
const peeked = ref<string | null | undefined>(undefined)
const shownSlug = computed(() => (props.drawer && peeked.value !== undefined ? peeked.value : currentSlug.value))
function peek(slug: string) {
  peeked.value = shownSlug.value === slug ? null : slug
}

// a click on another board keeps the page: «Доступ» → «Доступ»; from the account it's the board's settings
function samePage(slug: string): string {
  return `/app/boards/${slug}/${onAccess.value ? 'access' : 'settings'}`
}

// «Доступ» shows what works in the board's mode: the widget and the team on an open board, people on a closed one
function accessSections(b: BoardSummary): SectionLink[] {
  return b.visibility === 'private'
    ? [
        { hash: '#mode', label: 'Режим' },
        { hash: '#link', label: 'Ссылка' },
        ...(b.pending_requests ? [{ hash: '#requests', label: 'Запросы' }] : []),
        { hash: '#participants', label: 'Участники' },
      ]
    : [
        { hash: '#mode', label: 'Режим' },
        { hash: '#link', label: 'Ссылка' },
        { hash: '#widget', label: 'Виджет' },
        { hash: '#team', label: 'Команда' },
      ]
}

function rowTag(b: BoardSummary) {
  if (props.drawer) return 'button'
  return b.slug === currentSlug.value ? 'div' : RouterLink
}
function rowAttrs(b: BoardSummary) {
  if (props.drawer) return { type: 'button', 'aria-expanded': b.slug === shownSlug.value, onClick: () => peek(b.slug) }
  return b.slug === currentSlug.value ? { 'aria-current': 'true' } : { to: samePage(b.slug) }
}
</script>
