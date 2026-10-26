<template>
  <AppHeader />
  <main class="container base-page auth-page">
    <section class="auth-form" aria-live="polite">
      <template v-if="state === 'checking'">
        <h1>Проверяем ссылку…</h1>
        <p class="auth-intro">Это займёт секунду.</p>
      </template>

      <template v-else-if="state === 'done'">
        <h1>Почта подтверждена</h1>
        <p class="auth-intro">Спасибо! Напоминание в кабинете больше не появится.</p>
        <RouterLink v-if="auth.isAuth" to="/app/boards" class="btn btn-primary">К доскам</RouterLink>
        <RouterLink v-else to="/app/login" class="btn btn-primary">Войти</RouterLink>
      </template>

      <template v-else-if="state === 'expired'">
        <h1>Ссылка устарела</h1>
        <p class="auth-intro">
          Ссылка из письма действует сутки.
          {{ auth.isAuth ? 'Отправим новое — откройте ссылку из него.' : 'Войдите, и в кабинете можно будет запросить новое письмо.' }}
        </p>
        <button
          v-if="auth.isAuth"
          type="button"
          class="btn btn-primary"
          :disabled="sending || sent"
          :aria-busy="sending"
          @click="resend"
        >
          {{ sent ? 'Письмо отправлено' : 'Отправить новое письмо' }}
        </button>
        <RouterLink v-else to="/app/login" class="btn btn-primary">Войти</RouterLink>
      </template>

      <template v-else>
        <h1>Не получилось проверить ссылку</h1>
        <p class="auth-intro">{{ failure }}</p>
        <button type="button" class="btn btn-primary" @click="verify">Попробовать ещё раз</button>
      </template>
    </section>
  </main>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppHeader from '@/shared/components/AppHeader.vue'

// The link from the confirmation letter: /app/verify-email/:id/:hash?expires=…&signature=…
// Opens without a session (the letter may be read on another device); the signature is the proof.
const auth = useAuthStore()
const notification = useNotificationStore()
const route = useRoute()

const state = ref<'checking' | 'done' | 'expired' | 'failed'>('checking')
const failure = ref('')
const sending = ref(false)
const sent = ref(false)

function param(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

async function verify() {
  state.value = 'checking'
  try {
    await auth.verifyEmail(
      param(route.params.id),
      param(route.params.hash),
      param(route.query.expires),
      param(route.query.signature),
    )
    state.value = 'done'
  } catch (e) {
    // 403: a stale or tampered link
    if (e instanceof ApiError && e.status === 403) {
      // who's here decides the way out (resend or log in); an old letter opened after confirming is no error
      await auth.fetchMe().catch(() => undefined)
      state.value = auth.me?.email_verified ? 'done' : 'expired'
    } else {
      failure.value = errorMessage(e)
      state.value = 'failed'
    }
  }
}

async function resend() {
  if (sending.value || sent.value) return
  sending.value = true
  try {
    await auth.resendVerification()
    sent.value = true
    notification.success('Письмо отправлено. Проверьте и папку «Спам».')
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    sending.value = false
  }
}

onMounted(verify)
</script>