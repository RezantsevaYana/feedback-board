<template>
  <label class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <textarea v-model="model" class="input" :class="{ invalid: !!error }" v-bind="$attrs" />
    <span v-if="error" class="err" role="alert">{{ error }}</span>
  </label>
</template>

<script setup lang="ts">
// Textarea with an optional label, hint and error. Native attributes and listeners
// (rows, maxlength, placeholder, @input…) go to the <textarea>, not to the wrapper.
defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
defineProps<{
  label?: string
  hint?: string
  error?: string
}>()
</script>
