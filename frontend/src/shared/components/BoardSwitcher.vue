<template>
  <MenuComponent :items="items" align="left" class="board-switcher" aria-label="Переключить доску">
    <span class="board-switcher-label">{{ label }}</span><AppIcon name="chevron" />
  </MenuComponent>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { BoardSummary } from '@/api/types'
import AppIcon from '@/shared/ui/AppIcon.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'

// Drop-down with the admin's boards: in the /app/* header and on the board itself in team mode.
// `link` decides where a board leads — the board by default, the same settings tab in the admin.
// The trigger takes the caller's class (MenuComponent passes attrs to its button).
const props = defineProps<{
  boards: BoardSummary[]
  current: string | null
  label: string
  link?: (slug: string) => string
}>()
const route = useRoute()
const router = useRouter()

const items = computed<MenuItem[]>(() => [
  ...props.boards.map((b) => ({
    label: b.name,
    current: b.slug === props.current,
    run: () => router.push(props.link ? props.link(b.slug) : `/${b.slug}`),
  })),
  // the hub and the new-board page are sections too: the one you're on gets the check
  { label: 'Все доски', separated: true, current: route.name === 'hub', run: () => router.push('/app/boards') },
  { label: '+ Новая доска', current: route.name === 'board-new', run: () => router.push('/app/boards/new') },
])
</script>