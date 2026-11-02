<template>
  <!-- needed rarely: a row until «Сменить пароль», then the form in place -->
  <div v-if="!open" class="account-password-row">
    <div>
      <h2>Пароль</h2>
      <p class="note">Если его могли узнать — смените. Другие устройства останутся в аккаунте, на почту придёт письмо.</p>
    </div>
    <button type="button" class="btn" @click="openForm">Сменить пароль</button>
  </div>

  <form v-else ref="form" class="account-form" novalidate @submit.prevent="save">
    <h2>Сменить пароль</h2>
    <InputComponent
      v-model="current"
      label="Текущий пароль"
      type="password"
      autocomplete="current-password"
      :error="currentError"
      @input="currentError = ''"
    />
    <InputComponent
      v-model="password"
      label="Новый пароль"
      hint="от 8 символов"
      type="password"
      autocomplete="new-password"
      maxlength="72"
      :error="passwordError"
      @input="passwordError = ''"
    />
    <p class="note" aria-live="polite">
      <template v-if="resetSent">Ссылка для нового пароля отправлена на {{ auth.me?.email }}. Она действует час.</template>
      <template v-else>
        Не помните текущий?
        <button type="button" class="link-btn" :disabled="resetSending" :aria-busy="resetSending" @click="sendReset">Прислать ссылку на почту</button>
      </template>
    </p>
    <div class="account-password-actions">
      <button type="button" class="btn" :disabled="saving" @click="close">Отмена</button>
      <button class="btn btn-primary" type="submit" :disabled="!filled || saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сменить пароль' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'

// «Пароль»: folded into a row until «Сменить пароль»; then the current one and the new one, the session stays
// and a «Пароль изменён» letter goes out, the form folds back.
// A forgotten current password goes the reset way: the same letter as «Забыли пароль?» on the login page.
const auth = useAuthStore()
const notification = useNotificationStore()

const current = ref('')
const password = ref('')
const currentError = ref('')
const passwordError = ref('')
const saving = ref(false)
const resetSending = ref(false)
const resetSent = ref(false)
const filled = computed(() => !!current.value && !!password.value)
const open = ref(false)
const form = ref<HTMLFormElement | null>(null)

// the form opens with the caret in «Текущий пароль»
async function openForm() {
  open.value = true
  await nextTick()
  form.value?.querySelector('input')?.focus()
}
function close() {
  open.value = false
  current.value = ''
  password.value = ''
  currentError.value = ''
  passwordError.value = ''
}

async function save() {
  if (password.value.length < 8) {
    passwordError.value = 'Пароль — хотя бы 8 символов.'
    return
  }
  saving.value = true
  try {
    await auth.changePassword(current.value, password.value)
    notification.success('Пароль изменён. На почту ушло письмо об этом.')
    close()
  } catch (e) {
    const fields = e instanceof ApiError ? { current: e.field('current_password'), password: e.field('password') } : null
    if (fields?.current || fields?.password) {
      currentError.value = fields.current ?? ''
      passwordError.value = fields.password ?? ''
    } else {
      notification.error(errorMessage(e))
    }
  } finally {
    saving.value = false
  }
}

async function sendReset() {
  if (resetSending.value || !auth.me) return
  resetSending.value = true
  try {
    await auth.forgotPassword(auth.me.email)
    resetSent.value = true
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    resetSending.value = false
  }
}
</script>