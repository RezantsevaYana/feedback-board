<template>
  <button
    type="button"
    class="icon-btn copy-link"
    :title="copied ? 'Скопировано' : 'Копировать ссылку'"
    aria-label="Копировать ссылку"
    @click.stop="copy"
  >
    <AppIcon :name="copied ? 'check' : 'link'" />
  </button>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'

// Небольшая кнопка-иконка «копировать ссылку на предложение» в левый клипборд.
// Собирает абсолютный URL по маршруту `post`, чтобы его можно было отправить в чат.
const props = defineProps<{
  postId: number
}>()
const router = useRouter()
const board = useBoardStore()
const notification = useNotificationStore()

const copied = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

async function copy() {
  const slug = board.project?.slug
  if (!slug) return
  const path = router.resolve({ name: 'post', params: { slug, id: props.postId } }).href
  const url = `${window.location.origin}${path}`
  try {
    await navigator.clipboard.writeText(url)
    copied.value = true
    notification.success('Ссылка скопирована')
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => (copied.value = false), 1600)
  } catch {
    notification.error('Не получилось скопировать')
  }
}
</script>
