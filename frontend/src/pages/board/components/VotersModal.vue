<template>
  <BaseModal v-if="store.postId !== null" label="Кто голосовал" narrow @close="close">
    <div class="voters-top">
      <h2>Кто голосовал</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close"><AppIcon name="close" /></button>
    </div>
    <div class="voters-tabs" role="tablist">
      <button type="button" role="tab" :aria-selected="tab === 'like'" @click="tab = 'like'">
        👍 Нравится · {{ likes.length }}
      </button>
      <button type="button" role="tab" :aria-selected="tab === 'dislike'" @click="tab = 'dislike'">
        💩 Не нравится · {{ dislikes.length }}
      </button>
    </div>
    <div v-if="loading" class="note">Загружаем…</div>
    <div v-else-if="loadError" class="alert">{{ loadError }}</div>
    <ul v-else-if="current.length" class="voters-list">
      <li v-for="(v, i) in current" :key="i" class="voters-row">
        <span class="voters-name">{{ v.name }}</span>
        <span v-if="!v.account" class="voters-chip">гость</span>
      </li>
    </ul>
    <p v-else class="note">Пока никто.</p>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { PostsApi } from '@/api/posts'
import type { Voter, VoteValue } from '@/api/types'
import { errorMessage } from '@/api/client'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useVotersStore } from '@/store/modals/useVotersStore'

// Монтируется один раз на BoardPage. Открывается store.open(postId, 'like' | 'dislike') из VoteButtons.
// Эндпоинт /api/admin/posts/{id}/voters доступен только триажникам (owner/admin/manager) — VoteButtons
// сам решает, рисовать ли счётчик кликабельным.
const store = useVotersStore()
const voters = ref<Voter[]>([])
const loading = ref(false)
const loadError = ref('')
const tab = ref<VoteValue>('like')

const likes = computed(() => voters.value.filter((v) => v.kind === 'like'))
const dislikes = computed(() => voters.value.filter((v) => v.kind === 'dislike'))
const current = computed(() => (tab.value === 'like' ? likes.value : dislikes.value))

async function load(id: number) {
  loading.value = true
  loadError.value = ''
  voters.value = []
  try {
    const res = await PostsApi.voters(id)
    voters.value = res.data
  } catch (e) {
    loadError.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

watch(
  () => store.postId,
  (id) => {
    if (id !== null) {
      tab.value = store.initialKind
      load(id)
    }
  },
)

function close() {
  store.close()
}
</script>
