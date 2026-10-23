<template>
  <AppHeader />
  <main class="container base-page auth-page">
    <form class="auth-form" novalidate @submit.prevent="submit">
      <h1>Создать аккаунт</h1>
      <p class="auth-intro">Один аккаунт на все ваши продукты, у каждого своя доска.</p>
      <InputComponent
        v-model="name"
        label="Имя"
        type="text"
        autocomplete="name"
        maxlength="80"
        :errors="v$.name"
        autofocus
      />
      <InputComponent
        v-model="email"
        label="Email"
        type="text"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        autocomplete="email"
        maxlength="160"
        :errors="v$.email"
      />
      <InputComponent
        v-model="password"
        label="Пароль"
        hint="от 8 символов"
        type="password"
        autocomplete="new-password"
        maxlength="72"
        :errors="v$.password"
      />
      <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">
        {{ sending ? 'Создаём…' : 'Создать доску' }}
      </button>
      <p class="auth-switch">
        Уже есть аккаунт? <RouterLink to="/app/login">Войти</RouterLink>
      </p>
    </form>
  </main>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { email as emailRule, helpers, minLength, required } from '@vuelidate/validators'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

const auth = useAuthStore()
const notification = useNotificationStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const sending = ref(false)
const nameServer = ref('')
const emailServer = ref('')
const passwordServer = ref('')

const rules = {
  name: {
    required: helpers.withMessage('Представьтесь — хотя бы имя.', required),
    server: helpers.withMessage(() => nameServer.value, () => !nameServer.value),
  },
  email: {
    required: helpers.withMessage('Введите email.', required),
    email: helpers.withMessage('Проверьте email: пропущена @ или домен.', emailRule),
    server: helpers.withMessage(() => emailServer.value, () => !emailServer.value),
  },
  password: {
    required: helpers.withMessage('Придумайте пароль.', required),
    min: helpers.withMessage('Пароль — хотя бы 8 символов.', minLength(8)),
    server: helpers.withMessage(() => passwordServer.value, () => !passwordServer.value),
  },
}

const v$ = useVuelidate(rules, { name, email, password })

watch(name, () => (nameServer.value = ''))
watch(email, () => (emailServer.value = ''))
watch(password, () => (passwordServer.value = ''))

async function submit() {
  if (sending.value) return
  nameServer.value = ''
  emailServer.value = ''
  passwordServer.value = ''
  const isValid = await v$.value.$validate()
  if (!isValid) return

  sending.value = true
  try {
    await auth.register(name.value.trim(), email.value.trim(), password.value)
    notification.success('Аккаунт создан — создайте первую доску')
    router.push('/app/boards')
  } catch (e) {
    if (e instanceof ApiError) {
      nameServer.value = e.field('name') ?? ''
      emailServer.value = e.field('email') ?? ''
      passwordServer.value = e.field('password') ?? ''
      if (!nameServer.value && !emailServer.value && !passwordServer.value) emailServer.value = e.message
    } else {
      emailServer.value = String(e)
    }
    v$.value.$touch()
  } finally {
    sending.value = false
  }
}
</script>
