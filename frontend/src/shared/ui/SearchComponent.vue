<template>
  <div class="search-field" :class="{ narrow }">
    <AppIcon name="search" class="search-ico" />
    <input v-model="model" type="search" class="input" v-bind="$attrs" />
    <button v-if="model" type="button" class="search-clear" aria-label="Очистить" @click="model = ''">
      <AppIcon name="close" :size="14" />
    </button>
  </div>
</template>

<script setup lang="ts">
import AppIcon from './AppIcon.vue'

// Поле поиска: иконка-лупа слева и крестик сброса справа, когда что-то введено.
// Нативный крестик у type="search" спрятан в SCSS, чтобы не дублировать свой.
defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
defineProps<{
  narrow?: boolean
}>()
</script>
