<template>
  <div class="search-field" :class="{ narrow }">
    <AppIcon name="search" class="search-ico" />
    <input ref="input" v-model="model" type="search" class="input" v-bind="$attrs" />
    <button v-if="model" type="button" class="search-clear" aria-label="Очистить" @click="clear">
      <AppIcon name="close" :size="14" />
    </button>
    <kbd v-else-if="shortcut" class="search-key" aria-hidden="true">{{ shortcut }}</kbd>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AppIcon from './AppIcon.vue'

// Поле поиска: иконка-лупа слева и крестик сброса справа, когда что-то введено.
// Пока поле пустое, справа виден `shortcut` — клавиша, которая ставит в него фокус (её слушает страница).
// Нативный крестик у type="search" спрятан в SCSS, чтобы не дублировать свой.
defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
defineProps<{
  narrow?: boolean
  shortcut?: string
}>()

const input = ref<HTMLInputElement | null>(null)
function focus() {
  input.value?.focus()
  input.value?.select()
}
function clear() {
  model.value = ''
  input.value?.focus()
}
defineExpose({ focus })
</script>
