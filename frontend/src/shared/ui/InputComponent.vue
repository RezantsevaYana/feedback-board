<template>
  <label class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <!-- a password gets the eye inside the field: shows what was typed and hides it again -->
    <span v-if="isPassword" class="input-wrap">
      <input
        v-model="model"
        class="input"
        :class="{ invalid: hasError }"
        :aria-invalid="hasError || undefined"
        :aria-describedby="hasError ? errorId : undefined"
        v-bind="$attrs"
        :type="revealed ? 'text' : 'password'"
        @blur="onBlur"
      />
      <!-- mousedown.prevent keeps the focus and the caret in the field -->
      <button
        type="button"
        class="input-reveal"
        :aria-label="revealed ? 'Скрыть пароль' : 'Показать пароль'"
        :aria-pressed="revealed"
        @mousedown.prevent
        @click="revealed = !revealed"
      >
        <AppIcon :name="revealed ? 'eye-off' : 'eye'" :size="18" />
      </button>
    </span>
    <!-- a fixed part in front of what's typed, e.g. the service's host before a board's address -->
    <span v-else-if="prefix" class="input-group" :class="{ invalid: hasError }">
      <span class="input-prefix" aria-hidden="true">{{ prefix }}</span>
      <input
        v-model="model"
        class="input"
        :aria-invalid="hasError || undefined"
        :aria-describedby="hasError ? errorId : undefined"
        v-bind="$attrs"
        @blur="onBlur"
      />
    </span>
    <input
      v-else
      v-model="model"
      class="input"
      :class="{ invalid: hasError }"
      :aria-invalid="hasError || undefined"
      :aria-describedby="hasError ? errorId : undefined"
      v-bind="$attrs"
      @blur="onBlur"
    />
    <Transition name="field-err">
      <span v-if="hasError" :id="errorId" class="err" role="alert">{{ errorMessage }}</span>
    </Transition>
  </label>
</template>

<script setup lang="ts">
import { computed, isRef, ref, useAttrs, useId } from 'vue'
import AppIcon from './AppIcon.vue'

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
  /** a fixed part shown in front of the value, inside the field (e.g. «feedback.app/» before an address) */
  prefix?: string
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
// type="password" gets the eye; the field stays a password field until it's pressed
const attrs = useAttrs()
const isPassword = computed(() => attrs.type === 'password')
const revealed = ref(false)

// the field points at its error, so a screen reader reads both together
const errorId = useId()

function onBlur() {
  if (props.validateOnSubmit) return
  vuelidate.value?.$touch?.()
}
</script>
