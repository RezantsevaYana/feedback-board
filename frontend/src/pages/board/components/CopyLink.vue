<template>
  <button
    type="button"
    class="icon-btn copy-link"
    :class="{ copied }"
    :title="copied ? 'Скопировано' : 'Копировать ссылку'"
    aria-label="Копировать ссылку"
    @click.stop="copy(postId)"
  >
    <AppIcon :name="copied ? 'check' : 'link'" />
  </button>
</template>

<script setup lang="ts">
import AppIcon from '@/shared/ui/AppIcon.vue'
import { useCopyLink } from '../composables/useCopyLink'

// Small icon button: copy the link to the post (guests' card corner; the team gets CardMenu).
defineProps<{
  postId: number
}>()
const { copied, copy } = useCopyLink()
</script>