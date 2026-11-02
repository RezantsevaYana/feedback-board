<template>
  <fieldset class="radio-cards">
    <legend v-if="label" class="radio-cards-legend">{{ label }}</legend>
    <label v-for="o in options" :key="o.value" class="radio-card" :class="{ on: model === o.value }">
      <input v-model="model" type="radio" :name="name" :value="o.value" :disabled="disabled" class="radio-card-input" />
      <span class="radio-card-title">{{ o.label }}</span>
      <span v-if="o.hint" class="radio-card-hint">{{ o.hint }}</span>
    </label>
  </fieldset>
</template>

<script setup lang="ts" generic="T extends string">
// One choice of a few, each option a card with a title and a line on what it means
// (the board's mode on «Доступ»). A native radio group: arrows move between the cards, Space picks.
const model = defineModel<T>({ required: true })
defineProps<{
  name: string
  options: { value: T; label: string; hint?: string }[]
  label?: string
  disabled?: boolean
}>()
</script>