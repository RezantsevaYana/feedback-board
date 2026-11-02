<template>
  <div class="app-page platform-page">
    <div class="app-page-head">
      <h1>Админка сервиса</h1>
    </div>

    <div class="platform-tabs" role="tablist" aria-label="Разделы админки">
      <button type="button" role="tab" :aria-selected="tab === 'boards'" @click="tab = 'boards'">
        Доски<span v-if="boards" class="platform-tabs-count"> · {{ boards.length }}</span>
      </button>
      <button type="button" role="tab" :aria-selected="tab === 'users'" @click="tab = 'users'">
        Пользователи<span v-if="users" class="platform-tabs-count"> · {{ users.length }}</span>
      </button>
    </div>

    <p v-if="loadError" class="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="note">Загружаем…</p>
    <template v-else>
      <PlatformBoardsTable v-if="tab === 'boards' && boards" :boards="boards" />
      <PlatformUsersTable v-if="tab === 'users' && users" :users="users" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { errorMessage } from '@/api/client'
import { PlatformApi, type PlatformBoard, type PlatformUser } from '@/api/platform'
import PlatformBoardsTable from './components/PlatformBoardsTable.vue'
import PlatformUsersTable from './components/PlatformUsersTable.vue'

// /app/platform — «Админка сервиса», first iteration: every board and every account, read-only.
// Only superadmins get here (router meta.superadmin, the API checks it again).
const tab = ref<'boards' | 'users'>('boards')
const boards = ref<PlatformBoard[] | null>(null)
const users = ref<PlatformUser[] | null>(null)
const loading = ref(true)
const loadError = ref('')

onMounted(async () => {
  try {
    const [b, u] = await Promise.all([PlatformApi.boards(), PlatformApi.users()])
    boards.value = b.data
    users.value = u.data
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})
</script>
