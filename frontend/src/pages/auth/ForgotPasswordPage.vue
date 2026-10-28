<template>
  <AppHeader />
  <main class="container base-page auth-page">
    <section v-if="sentTo" class="auth-form" aria-live="polite">
      <h1>Проверьте почту</h1>
      <p class="auth-intro">
        Если аккаунт с адресом <strong>{{ sentTo }}</strong> есть, на него придёт письмо со ссылкой. Она действует час.
        Письма нет — загляните в «Спам».
      </p>
      <button type="button" class="btn" :disabled="sending" :aria-busy="sending" @click="send">Отправить ещё раз</button>
      <p class="auth-switch">
        <button type="button" class="link-btn" @click="sentTo = ''">Указать другой email</button> · <RouterLink to="/app/login">Войти</RouterLink>
      </p>
    </section>

    <form v-else class="auth-form" novalidate @submit.prevent="submit">
      <h1>Забыли пароль?</h1>
      <p class="auth-intro">Пришлём ссылку, по которой можно задать новый.</p>
      <InputComponent
        v-model="email"
        label="Email"
        type="text"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        autocomplete="username"
        maxlength="160"
        :errors="v$.email"
        validate-on-submit
        autofocus
      />
      <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">
        {{ sending ? 'Отправляем…' : 'Прислать ссылку' }}
      </button>
      <p class="auth-switch">
        Вспомнили? <RouterLink to="/app/login">Войти</RouterLink>
      </p>
    </form>
  </main>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { email as emailRule, helpers, required } from '@vuelidate/validators'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

const auth = useAuthStore()
const notification = useNotificationStore()
const route = useRoute()

// the login page and a stale reset link pass the address along, so it isn't typed twice
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const sending = ref(false)
const sentTo = ref('')
const emailServer = ref('')

const rules = {
  email: {
    required: helpers.withMessage('Введите email.', required),
    email: helpers.withMessage('Проверьте email: пропущена @ или домен.', emailRule),
    server: helpers.withMessage(() => emailServer.value, () => !emailServer.value),
  },
}

const v$ = useVuelidate(rules, { email })

watch(email, () => (emailServer.value = ''))

async function submit() {
  if (sending.value) return
  emailServer.value = ''
  const isValid = await v$.value.$validate()
  if (!isValid) return
  await send()
}

async function send() {
  if (sending.value) return
  sending.value = true
  const address = email.value.trim()
  try {
    await auth.forgotPassword(address)
    if (sentTo.value) notification.success('Письмо отправлено ещё раз.')
    sentTo.value = address
  } catch (e) {
    if (e instanceof ApiError && e.field('email') && !sentTo.value) {
      emailServer.value = e.field('email') ?? ''
      v$.value.$touch()
    } else {
      notification.error(errorMessage(e))
    }
  } finally {
    sending.value = false
  }
}
</script>