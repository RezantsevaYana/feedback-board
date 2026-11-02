<template>
  <!-- focusable, so a tap on a phone and Tab on a keyboard open the hint too, not only the mouse -->
  <span class="visibility-badge" :class="visibility" tabindex="0">
    <AppIcon :name="visibility === 'private' ? 'lock' : 'globe'" :size="14" />
    <span class="visibility-badge-label">{{ visibility === 'private' ? 'Закрытая' : 'Открытая' }}</span>
    <span class="visibility-badge-hint" role="tooltip">{{ hint }}</span>
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
