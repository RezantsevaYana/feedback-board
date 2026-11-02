<template>
  <div class="sidebar-account">
    <div class="sidebar-account-who">
      <span class="sidebar-account-name">{{ name }}</span>
      <span class="sidebar-account-mail" :title="email">{{ email }}</span>
      <span v-if="chip" class="chip">{{ chip }}</span>
    </div>
    <nav class="sidebar-account-links" aria-label="Аккаунт">
      <RouterLink v-if="showBoards" to="/app/boards" class="sidebar-link">Мои доски</RouterLink>
      <RouterLink to="/app/account" class="sidebar-link" :aria-current="route.name === 'account' ? 'page' : undefined">Настройки аккаунта</RouterLink>
      <!-- «Выйти» with whatever the menu puts beside it (the board's drop-down: the theme), one last row -->
      <div class="sidebar-account-out">
        <button type="button" class="sidebar-link sidebar-link-out" :disabled="busy" :aria-busy="busy" @click="logout">
          {{ busy ? 'Выходим…' : 'Выйти' }}
        </button>
        <slot name="after-out" />
      </div>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { useLogout } from '@/shared/composables/useLogout'

// The bottom of every side menu, on the board and in the cabinet: who is signed in, their settings and
// the way out, always in sight instead of behind a drop-down (TEST.md п. 3).
defineProps<{
  name: string
  email: string
  /** the role on this board, on the board's own menu */
  chip?: string
  /** the board's menu also leads back to the cabinet; the cabinet has «Мои доски» at the top */
  showBoards?: boolean
}>()
const route = useRoute()
const { logout, busy } = useLogout()
</script>
