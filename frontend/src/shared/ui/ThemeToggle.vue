<template>
  <button
    type="button"
    class="theme-toggle"
    :title="`Тема: ${label}. Переключить`"
    :aria-label="`Тема: ${label}. Переключить`"
    @click="cycle"
  >
    <AppIcon :name="icon" />
    <span class="theme-toggle-label">{{ label }}</span>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import { useTheme } from '@/shared/composables/useTheme'

// Трёхпозиционный переключатель: Авто (по системе) → Светлая → Тёмная → Авто.
// Текущий выбор сохраняется в localStorage.
const { theme, cycle } = useTheme()

const icon = computed(() =>
  theme.value === 'light' ? ('sun' as const) : theme.value === 'dark' ? ('moon' as const) : ('monitor' as const),
)
const label = computed(() => (theme.value === 'light' ? 'светлая' : theme.value === 'dark' ? 'тёмная' : 'авто'))
</script>
