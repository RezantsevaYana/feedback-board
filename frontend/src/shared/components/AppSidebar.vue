<template>
  <div class="app-sidebar">
    <nav class="app-sidebar-nav" aria-label="Кабинет">
      <RouterLink to="/app/boards" class="nav-item" :aria-current="atHub ? 'page' : undefined">Мои доски</RouterLink>
    </nav>

    <!-- the board this page belongs to and its two pages; another board is picked in the header -->
    <section v-if="board" class="app-sidebar-board" aria-label="Доска">
      <p class="app-sidebar-board-name">{{ board.name }}</p>
      <nav class="app-sidebar-nav" :aria-label="`Страницы доски ${board.name}`">
        <RouterLink :to="settingsPath" class="nav-item" :aria-current="onSettings ? 'page' : undefined">Настройки</RouterLink>
        <!-- the open page's sections fold out under it; both lists stay in the DOM so the switch animates -->
        <div class="app-sidebar-sub" :class="{ open: onSettings }" :inert="!onSettings">
          <SectionLinks :path="settingsPath" :sections="SETTINGS_SECTIONS" :active="onSettings" />
        </div>
        <RouterLink :to="accessPath" class="nav-item" :aria-current="onAccess ? 'page' : undefined">
          Доступ<span v-if="board.pending_requests" class="count-badge" :aria-label="`запросов на доступ: ${board.pending_requests}`">{{ board.pending_requests }}</span>
        </RouterLink>
        <div class="app-sidebar-sub" :class="{ open: onAccess }" :inert="!onAccess">
          <SectionLinks :key="board.visibility" :path="accessPath" :sections="accessSections" :active="onAccess" />
        </div>
      </nav>
    </section>

    <!-- the account sits in the same menu as the board's pages, with its sections, not in a place of its own -->
    <section class="app-sidebar-board app-sidebar-account" aria-label="Аккаунт">
      <nav class="app-sidebar-nav" aria-label="Аккаунт">
        <RouterLink to="/app/account" class="nav-item" :aria-current="onAccount ? 'page' : undefined">Настройки аккаунта</RouterLink>
        <div class="app-sidebar-sub" :class="{ open: onAccount }" :inert="!onAccount">
          <SectionLinks path="/app/account" :sections="ACCOUNT_SECTIONS" :active="onAccount" />
        </div>
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
import { computed, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useLogout } from '@/shared/composables/useLogout'
import { canConfigure } from '@/shared/utils/roles'
import SectionLinks, { type SectionLink } from '@/shared/components/SectionLinks.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'

// The cabinet's side menu (AppShell), under the header: «Мои доски», the current board's two pages
// («Настройки» — what the board is, «Доступ» — who sees it and how they get in) with their sections, the account
// with its sections, «Выйти» and the theme at the bottom. The same content slides in as a panel on tablet and phone.
const auth = useAuthStore()
const route = useRoute()
const { logout, busy } = useLogout()

const SETTINGS_SECTIONS: SectionLink[] = [
  { hash: '#general', label: 'Общее' },
  { hash: '#categories', label: 'Категории' },
  { hash: '#statuses', label: 'Столбцы' },
]

const ACCOUNT_SECTIONS: SectionLink[] = [
  { hash: '#profile', label: 'Профиль' },
  { hash: '#password', label: 'Пароль' },
]

const atHub = computed(() => route.name === 'hub' || route.name === 'board-new')
const onSettings = computed(() => route.name === 'board-settings')
const onAccess = computed(() => route.name === 'board-access')
const onAccount = computed(() => route.name === 'account')
// on a board's page that board is remembered; the account page keeps showing it, so its pages stay one click away
watch(() => route.params.slug, (slug) => {
  if (slug) auth.lastBoardSlug = String(slug)
}, { immediate: true })
// a board's own pages — only for those who may configure it
const board = computed(() => {
  const slug = route.params.slug ? String(route.params.slug) : onAccount.value ? auth.lastBoardSlug : null
  // every board the user is on (an admin of someone else's board too), not only the ones they own
  const found = slug ? auth.me?.projects.find((b) => b.slug === slug) : undefined
  return found && canConfigure(found.role) ? found : null
})
const settingsPath = computed(() => `/app/boards/${board.value?.slug}/settings`)
const accessPath = computed(() => `/app/boards/${board.value?.slug}/access`)
// «Доступ» shows what works in the board's mode: the widget and the team on an open board, people on a closed one
const accessSections = computed<SectionLink[]>(() =>
  board.value?.visibility === 'private'
    ? [
        { hash: '#mode', label: 'Режим' },
        { hash: '#link', label: 'Ссылка' },
        ...(board.value.pending_requests ? [{ hash: '#requests', label: 'Запросы' }] : []),
        { hash: '#participants', label: 'Участники' },
      ]
    : [
        { hash: '#mode', label: 'Режим' },
        { hash: '#link', label: 'Ссылка' },
        { hash: '#widget', label: 'Виджет' },
        { hash: '#team', label: 'Команда' },
      ],
)
</script>