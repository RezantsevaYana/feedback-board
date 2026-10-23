<template>
  <header class="app-shell-header">
    <div class="container app-shell-header-inner">
      <div class="app-shell-nav">
        <RouterLink :to="brandTarget" class="app-brand">Feedback Board</RouterLink>
        <template v-if="auth.isAuth && auth.boardSummaries.length">
          <span class="app-shell-sep" aria-hidden="true">/</span>
          <BoardSwitcher :boards="auth.boardSummaries" :current="currentSlug" :label="switcherLabel" :link="switchLink" />
        </template>
      </div>
      <div v-if="auth.isAuth" class="app-shell-user">
        <span class="app-shell-email">{{ auth.me?.email }}</span>
        <button type="button" class="btn" :disabled="loggingOut" :aria-busy="loggingOut" @click="onLogout">Выход</button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import BoardSwitcher from '@/shared/components/BoardSwitcher.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const loggingOut = ref(false)

const brandTarget = computed(() => (auth.isAuth ? '/app/boards' : '/'))

// on a board's settings or embed page the switcher names that board and keeps the same page for the next one;
// elsewhere (hub, new board) it reads «Доски» and opens the chosen board
const currentSlug = computed(() => (route.params.slug ? String(route.params.slug) : null))
const switcherLabel = computed(() => auth.boardSummaries.find((b) => b.slug === currentSlug.value)?.name ?? 'Доски')
const switchLink = computed(() => {
  const page = route.name === 'board-settings' ? 'settings' : route.name === 'board-embed' ? 'embed' : null
  return page ? (slug: string) => `/app/boards/${slug}/${page}` : undefined
})

async function onLogout() {
  if (loggingOut.value) return
  loggingOut.value = true
  try {
    await auth.logout()
    router.push('/app/login')
  } finally {
    loggingOut.value = false
  }
}
</script>
