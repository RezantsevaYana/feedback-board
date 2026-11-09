<template>
  <button
    type="button"
    class="switch"
    role="switch"
    :aria-checked="model"
    :aria-label="ariaLabel"
    :disabled="disabled"
    @click="model = !model"
  >
    <span class="switch-knob" aria-hidden="true" />
  </button>
</template>

<script setup lang="ts">
// A pill-style toggle (40×24) used for settings switches (prototype frontend/demo/settings-rework-prototype.html).
// Label and explanation live in the parent — this component is just the control itself (like CheckboxComponent).
defineOptions({ inheritAttrs: true })
const model = defineModel<boolean>({ required: true })
defineProps<{
  ariaLabel: string
  disabled?: boolean
}>()
</script>
