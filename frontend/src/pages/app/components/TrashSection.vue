<template>
  <section class="trash-section" aria-labelledby="trash-title">
    <h2 id="trash-title">Корзина</h2>
    <p class="note">Удалённые доски хранятся здесь, а потом удаляются навсегда вместе с предложениями.</p>
    <ul class="trash-list">
      <li v-for="b in boards" :key="b.id" class="trash-item">
        <div class="trash-item-info">
          <span class="trash-item-name">{{ b.name }}</span>
          <code class="trash-item-slug">/{{ b.slug }}</code>
        </div>
        <span class="trash-item-purge">удалится {{ fmtDate(b.purge_at) }}</span>
        <button type="button" class="btn" :disabled="restoring === b.slug" :aria-busy="restoring === b.slug" @click="restore(b)">
          {{ restoring === b.slug ? 'Восстанавливаем…' : 'Восстановить' }}
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { TrashedBoard } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { fmtDate } from '@/shared/utils/format'

// Hub's trash: boards deleted in settings, until PurgeTrashedBoards removes them (purge_at).
defineProps<{
  boards: TrashedBoard[]
}>()
const auth = useAuthStore()
const notification = useNotificationStore()
const restoring = ref<string | null>(null)

async function restore(b: TrashedBoard) {
  restoring.value = b.slug
  try {
    await auth.restoreBoard(b.slug)
    notification.success(`«${b.name}» снова работает`)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    restoring.value = null
  }
}
</script>
