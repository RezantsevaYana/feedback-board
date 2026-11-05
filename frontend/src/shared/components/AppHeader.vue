<template>
  <header class="app-shell-header">
    <div class="container app-shell-header-inner">
      <div class="app-shell-nav">
        <RouterLink :to="brandTarget" class="app-brand">Feedback Board</RouterLink>
        <!-- phone: the wordmark gives way to the short brand tag, so the board's name gets the row -->
        <RouterLink :to="brandTarget" class="app-brand-mark slant" aria-label="Feedback Board">FB</RouterLink>
        <template v-if="auth.isAuth && auth.boardSummaries.length">
          <span class="app-shell-sep" aria-hidden="true">/</span>
          <BoardSwitcher :boards="auth.boardSummaries" :current="currentSlug" :label="switcherLabel" :link="switchLink" />
        </template>
      </div>
      <div v-if="auth.isAuth" class="app-shell-user">
        <!-- inside the cabinet «Выйти» is at the bottom of the side menu; the pages around it keep it here -->
        <AccountMenu :with-logout="!menu" :extra-items="extraMenuItems" />
        <!-- tablet and phone, inside the cabinet: the side menu opens as a panel -->
        <button v-if="menu" type="button" class="btn app-shell-menu" aria-label="Меню" @click="emit('menu')">
          <BurgerIcon /><span class="app-shell-menu-label">Меню</span>
        </button>
      </div>
    </div>
  </header>
  <!-- the verify page says the same itself, with its own button -->
  <!-- «Настройки аккаунта» shows it inside the profile, next to the address it's about -->
  <div v-if="auth.me && !auth.me.email_verified && route.name !== 'verify-email' && route.name !== 'account'" class="container">
    <VerifyEmailBanner :email="auth.me.email" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AccountMenu from '@/shared/components/AccountMenu.vue'
import BoardSwitcher from '@/shared/components/BoardSwitcher.vue'
import VerifyEmailBanner from '@/shared/components/VerifyEmailBanner.vue'
import BurgerIcon from '@/shared/ui/BurgerIcon.vue'
import type { MenuItem } from '@/shared/ui/MenuComponent.vue'
import { canConfigure } from '@/shared/utils/roles'

// Top bar of every /app/* page: the board switcher and the account. Inside the cabinet (AppShell) the side menu
// sits under it with the board's places; `menu` adds the button that opens that menu on tablet and phone.
defineProps<{
  menu?: boolean
  /** extra entries added to the account dropdown — used on the board page for «Быстрая настройка» etc. */
  extraMenuItems?: MenuItem[]
}>()
const emit = defineEmits<{
  menu: []
}>()
const auth = useAuthStore()
const route = useRoute()

const brandTarget = computed(() => (auth.isAuth ? '/app/boards' : '/'))

// on a board's page the switcher names that board and keeps the same page for the next one (access →
// access); elsewhere it reads «Доски» and opens the chosen board's settings (TEST.md п. 17).
// A board the user can't configure opens as the board itself.
const PLACES: Record<string, string> = { 'board-settings': 'settings', 'board-access': 'access' }
const currentSlug = computed(() => (route.params.slug ? String(route.params.slug) : null))
const switcherLabel = computed(() => auth.boardSummaries.find((b) => b.slug === currentSlug.value)?.name ?? 'Доски')
function switchLink(slug: string) {
  const board = auth.boardSummaries.find((b) => b.slug === slug)
  if (!canConfigure(board?.role)) return `/${slug}`
  return `/app/boards/${slug}/${PLACES[String(route.name)] ?? 'settings'}`
}
</script>
