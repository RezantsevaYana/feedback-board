<template>
  <fieldset class="radio-group" :disabled="disabled">
    <legend v-if="legend" class="radio-group-legend">{{ legend }}</legend>
    <label v-for="o in options" :key="String(o.value)" class="radio-group-row">
      <input v-model="model" type="radio" :name="name" :value="o.value" />
      <span class="radio-group-text">
        <span class="radio-group-label">{{ o.label }}</span>
        <small v-if="o.hint" class="radio-group-hint">{{ o.hint }}</small>
      </span>
    </label>
  </fieldset>
</template>

<script setup lang="ts" generic="T extends string | boolean | number">
// One choice of a few, plain-radio style (not cards, not a switch). Each option is a label plus a short line
// on what it means (prototype frontend/demo/settings-rework-prototype.html: «Кто может оставлять предложения»,
// «Кто может голосовать», п. 40). A native radio group — arrows move between options, Space picks.
const model = defineModel<T>({ required: true })
defineProps<{
  name: string
  options: { value: T; label: string; hint?: string }[]
  legend?: string
  disabled?: boolean
}>()
</script>
