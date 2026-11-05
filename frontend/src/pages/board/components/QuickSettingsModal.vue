<template>
  <BaseModal label="Быстрая настройка" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Быстрая настройка</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <div class="quick-settings-tabs" role="tablist" aria-label="Что настроить">
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'statuses'"
        :aria-pressed="tab === 'statuses'"
        class="quick-settings-tab"
        @click="tab = 'statuses'"
      >
        Статусы
      </button>
      <button
        type="button"
        role="tab"
        :aria-selected="tab === 'categories'"
        :aria-pressed="tab === 'categories'"
        class="quick-settings-tab"
        @click="tab = 'categories'"
      >
        Категории
      </button>
    </div>
    <StatusesEditor v-if="tab === 'statuses'" />
    <CategoriesEditor v-else />
  </BaseModal>
</template>

<script setup lang="ts">
import { inject, ref } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import CategoriesEditor from '@/shared/components/CategoriesEditor.vue'
import StatusesEditor from '@/shared/components/StatusesEditor.vue'

// Combined «Статусы» + «Категории» editor under one «Быстрая настройка» entry from the board top menu
// (test.md п. 14). The initial tab comes from which action in the menu opened the modal.
type Tab = 'statuses' | 'categories'
const props = withDefaults(defineProps<{ initial?: Tab }>(), { initial: 'statuses' })
const emit = defineEmits<{ close: [] }>()
const close = inject<() => void>('baseModal:close', () => emit('close'))
const tab = ref<Tab>(props.initial)
</script>
