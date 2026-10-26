<template>
  <label class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <input
      v-model="model"
      class="input"
      :class="{ invalid: hasError }"
      :aria-invalid="hasError || undefined"
      :aria-describedby="hasError ? errorId : undefined"
      v-bind="$attrs"
      @blur="onBlur"
    />
    <span v-if="hasError" :id="errorId" class="err" role="alert">{{ errorMessage }}</span>
  </label>
</template>

<script setup lang="ts">
import { computed, isRef, useId } from 'vue'

// Текстовый инпут. Нативные атрибуты (type, maxlength, placeholder, @input…) падают на <input>.
// Ошибку принимает в двух форматах:
//   :error="строка"   — для серверных сообщений (ApiError 422) и ручного управления;
//   :errors="v$.foo"  — для клиентской валидации через @vuelidate: показываем первое $message,
//                       только после $dirty (сработало после blur или ручного $touch).
// validateOnSubmit — не трогать поле на blur: ошибки появятся только после $validate() на submit.
type VuelidateMessage = string | { value: string }
type VuelidateField = { $dirty?: boolean; $errors?: { $message: VuelidateMessage }[]; $touch?: () => void }

defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
const props = defineProps<{
  label?: string
  hint?: string
  error?: string
  // приходит как `v$.field` — у него плотный тип vuelidate, принимаем через unknown
  errors?: unknown
  validateOnSubmit?: boolean
}>()

const vuelidate = computed(() => props.errors as VuelidateField | undefined)

const vuelidateMessage = computed(() => {
  const field = vuelidate.value
  const raw = field?.$errors?.[0]?.$message
  if (!field?.$dirty || !raw) return ''
  return isRef(raw) ? (raw.value as string) : (raw as string)
})
const errorMessage = computed(() => props.error || vuelidateMessage.value)
const hasError = computed(() => !!errorMessage.value)
// the field points at its error, so a screen reader reads both together
const errorId = useId()

function onBlur() {
  if (props.validateOnSubmit) return
  vuelidate.value?.$touch?.()
}
</script>
