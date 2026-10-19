<template>
  <label class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <select v-model="model" class="input" v-bind="$attrs">
      <option v-for="option in options" :key="String(option.value)" :value="option.value">{{ option.label }}</option>
    </select>
  </label>
</template>

<script setup lang="ts" generic="T extends string | number | null">
export interface SelectOption<V> {
  value: V
  label: string
}

// Native select with an optional label and hint. Native attributes (aria-label…) go to the <select>.
defineOptions({ inheritAttrs: false })
const model = defineModel<T>({ required: true })
defineProps<{
  options: SelectOption<T>[]
  label?: string
  hint?: string
}>()
</script>
