<template>
  <AppHeader />
  <main class="container base-page auth-page">
    <form class="auth-form" novalidate @submit.prevent="submit">
      <h1>Войти</h1>
      <p class="auth-intro">Войдите, чтобы вернуться к своим доскам.</p>
      <InputComponent
        v-model="email"
        label="Email"
        type="text"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        autocomplete="username"
        :errors="v$.email"
        autofocus
      />
      <InputComponent
        v-model="password"
        label="Пароль"
        type="password"
        autocomplete="current-password"
        :errors="v$.password"
      />
      <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">
        {{ sending ? 'Входим…' : 'Войти' }}
      </button>
      <p class="auth-switch">
        Нет аккаунта? <RouterLink to="/app/register">Создать</RouterLink>
      </p>
    </form>
  </main>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { email as emailRule, helpers, required } from '@vuelidate/validators'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const sending = ref(false)
const emailServer = ref('')
const passwordServer = ref('')

const rules = {
  email: {
    required: helpers.withMessage('Введите email.', required),
    email: helpers.withMessage('Проверьте email: пропущена @ или домен.', emailRule),
    server: helpers.withMessage(() => emailServer.value, () => !emailServer.value),
  },
  password: {
    required: helpers.withMessage('Введите пароль.', required),
    server: helpers.withMessage(() => passwordServer.value, () => !passwordServer.value),
  },
}

const v$ = useVuelidate(rules, { email, password })

watch(email, () => (emailServer.value = ''))
watch(password, () => (passwordServer.value = ''))

async function submit() {
  if (sending.value) return
  emailServer.value = ''
  passwordServer.value = ''
  const isValid = await v$.value.$validate()
  if (!isValid) return

  sending.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    const next = typeof router.currentRoute.value.query.next === 'string' ? router.currentRoute.value.query.next : '/app/boards'
    router.push(next)
  } catch (e) {
    if (e instanceof ApiError) {
      emailServer.value = e.field('email') ?? ''
      passwordServer.value = e.field('password') ?? ''
      if (!emailServer.value && !passwordServer.value) passwordServer.value = e.message
    } else {
      passwordServer.value = String(e)
    }
    v$.value.$touch()
  } finally {
    sending.value = false
  }
}
</script>
