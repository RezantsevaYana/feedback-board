<template>
  <!-- a bottom sheet on a phone (BaseModal), a small dialog elsewhere -->
  <BaseModal v-if="store.postId !== null" label="Кто голосовал" narrow @close="close">
    <div class="voters-top">
      <div class="voters-top-text">
        <h2>Кто голосовал</h2>
        <p v-if="store.postTitle" class="voters-post">{{ store.postTitle }}</p>
      </div>
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
        <span class="voter-face" :class="[`tone-${i % 3}`, { guest: !v.account && v.name === 'Аноним' }]" aria-hidden="true">{{ v.account || v.name !== 'Аноним' ? initials(v.name, null) : '?' }}</span>
        <span class="voters-name">{{ v.name }}</span>
        <span v-if="!v.account" class="voters-chip">гость</span>
      </li>
    </ul>
    <p v-else class="note">Пока никто.</p>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Voter, VoteValue } from '@/api/types'
import { errorMessage } from '@/api/client'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useVotersStore } from '@/store/modals/useVotersStore'
import { initials } from '@/shared/utils/format'

// Монтируется один раз на BoardPage. Открывается store.open(postId, kind, title) по аватаркам в VoteButtons.
// Эндпоинт /api/admin/posts/{id}/voters доступен только триажникам (owner/admin/manager) — VoteButtons
// сам решает, показывать ли аватарки; список берётся из кеша стора, как и у всплывающего списка.
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
    voters.value = await store.load(id)
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
