<template>
  <span class="visibility-badge" :class="visibility" :title="hint">
    <AppIcon :name="visibility === 'private' ? 'lock' : 'globe'" :size="13" />
    <span class="visibility-badge-label">{{ visibility === 'private' ? 'Закрытая' : 'Открытая' }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'

// Open or closed board, in a word and an icon: on the hub card and on the board itself.
// The hint says what it means, since «открытая» alone doesn't say who sees what.
const props = defineProps<{
  visibility: 'public' | 'private'
}>()

const hint = computed(() => (props.visibility === 'private'
  ? 'Закрытая доска: видят только участники'
  : 'Открытая доска: видят все, у кого есть ссылка'))
</script>
