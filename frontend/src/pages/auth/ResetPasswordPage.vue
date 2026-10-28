<template>
  <AppHeader />
  <main class="container base-page auth-page">
    <section v-if="expired" class="auth-form" aria-live="polite">
      <h1>Ссылка устарела</h1>
      <p class="auth-intro">{{ expired }}</p>
      <RouterLink :to="{ path: '/app/forgot-password', query: email ? { email } : {} }" class="btn btn-primary">
        Прислать новую ссылку
      </RouterLink>
      <p class="auth-switch">
        Вспомнили пароль? <RouterLink to="/app/login">Войти</RouterLink>
      </p>
    </section>

    <form v-else class="auth-form" novalidate @submit.prevent="submit">
      <h1>Новый пароль</h1>
      <p class="auth-intro">
        Для <strong>{{ email }}</strong>. После сохранения сразу войдём в аккаунт.
      </p>
      <InputComponent
        v-model="password"
        label="Новый пароль"
        hint="от 8 символов"
        type="password"
        autocomplete="new-password"
        maxlength="72"
        :errors="v$.password"
        validate-on-submit
        autofocus
      />
      <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">
        {{ sending ? 'Сохраняем…' : 'Сохранить и войти' }}
      </button>
    </form>
  </main>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { helpers, minLength, required } from '@vuelidate/validators'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// The link from the reset letter: /app/reset-password/:token?email=…
const auth = useAuthStore()
const notification = useNotificationStore()
const route = useRoute()
const router = useRouter()

const token = String(route.params.token)
const email = typeof route.query.email === 'string' ? route.query.email : ''

const password = ref('')
const sending = ref(false)
const passwordServer = ref('')
// a stale, used or cut-off link: the message to show instead of the form
const expired = ref(email ? '' : 'В ссылке не хватает адреса почты. Запросите новое письмо.')

const rules = {
  password: {
    required: helpers.withMessage('Придумайте пароль.', required),
    minLength: helpers.withMessage('Пароль — хотя бы 8 символов.', minLength(8)),
    server: helpers.withMessage(() => passwordServer.value, () => !passwordServer.value),
  },
}

const v$ = useVuelidate(rules, { password })

watch(password, () => (passwordServer.value = ''))

async function submit() {
  if (sending.value) return
  passwordServer.value = ''
  const isValid = await v$.value.$validate()
  if (!isValid) return

  sending.value = true
  try {
    await auth.resetPassword(token, email, password.value)
    notification.success('Пароль изменён.')
    router.push('/app/boards')
  } catch (e) {
    if (e instanceof ApiError && (e.field('token') || e.field('email'))) {
      expired.value = e.field('token') ?? e.field('email') ?? ''
    } else {
      passwordServer.value = e instanceof ApiError ? (e.field('password') ?? e.message) : errorMessage(e)
      v$.value.$touch()
    }
  } finally {
    sending.value = false
  }
}
</script>