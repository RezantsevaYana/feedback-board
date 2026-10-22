<template>
  <header class="app-shell-header">
    <div class="container app-shell-header-inner">
      <RouterLink :to="brandTarget" class="app-brand">Feedback Board</RouterLink>
      <div v-if="auth.isAuth" class="app-shell-user">
        <span class="app-shell-email">{{ auth.me?.email }}</span>
        <button type="button" class="btn" :disabled="loggingOut" :aria-busy="loggingOut" @click="onLogout">Выход</button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'

const auth = useAuthStore()
const router = useRouter()
const loggingOut = ref(false)

const brandTarget = computed(() => (auth.isAuth ? '/app/boards' : '/'))

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
