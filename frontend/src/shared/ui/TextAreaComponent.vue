<template>
  <label class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <textarea
      v-model="model"
      class="input"
      :class="{ invalid: !!error }"
      :aria-invalid="!!error || undefined"
      :aria-describedby="error ? errorId : undefined"
      v-bind="$attrs"
    />
    <Transition name="field-err">
      <span v-if="error" :id="errorId" class="err" role="alert">{{ error }}</span>
    </Transition>
  </label>
</template>

<script setup lang="ts">
import { useId } from 'vue'

// Textarea with an optional label, hint and error. Native attributes and listeners
// (rows, maxlength, placeholder, @input…) go to the <textarea>, not to the wrapper.
defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
defineProps<{
  label?: string
  hint?: string
  error?: string
}>()
// the field points at its error, so a screen reader reads both together
const errorId = useId()
</script>
