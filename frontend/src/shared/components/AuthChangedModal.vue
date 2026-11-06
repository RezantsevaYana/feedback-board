<template>
  <BaseModal v-if="text" label="Сессия изменилась" narrow persistent>
    <div class="drawer-top">
      <h2>Сессия изменилась</h2>
    </div>
    <p class="desc">{{ text }} Перезагрузите страницу, чтобы продолжить.</p>
    <div class="row confirm-actions">
      <button type="button" class="btn btn-primary" autofocus @click="reload">Перезагрузить</button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthBroadcast } from '@/shared/composables/useAuthBroadcast'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import BaseModal from '@/shared/ui/BaseModal.vue'

// Another tab logged in, out or as someone else: this tab's stores belong to the previous session, so the only
// way on is a reload — one button, no «Отмена», no Esc / background click (TEST.md п. 25). Mounted once in App.vue.
const auth = useAuthStore()
const board = useBoardStore()
const text = ref('')

useAuthBroadcast(({ userId }) => {
  // the cabinet knows the user from auth, the board page from its own store; whichever this tab has loaded
  const known = auth.me?.id ?? board.me?.id ?? null
  if (userId === known) return
  if (userId === null) text.value = 'Вы вышли из аккаунта в другой вкладке.'
  else if (known === null) text.value = 'Вы вошли в аккаунт в другой вкладке.'
  else text.value = 'Вы вошли под другим пользователем в другой вкладке.'
})

function reload() {
  window.location.reload()
}
</script>
