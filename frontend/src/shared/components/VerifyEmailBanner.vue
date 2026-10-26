<template>
  <div class="container">
    <div class="verify-banner" role="status">
      <p class="verify-banner-text">
        <strong>Подтвердите почту.</strong>
        Откройте ссылку из письма, которое мы прислали на <span class="verify-banner-email">{{ auth.me?.email }}</span>,
        чтобы убедиться, что адрес ваш. Доски работают и без этого.
      </p>
      <button
        type="button"
        class="btn"
        :disabled="sending || sent"
        :aria-busy="sending"
        @click="resend"
      >
        {{ sent ? 'Письмо отправлено' : 'Отправить ещё раз' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

// Soft email confirmation: shown under AppHeader until the letter's link is opened (see VerifyEmailPage).
const auth = useAuthStore()
const notification = useNotificationStore()
const sending = ref(false)
// after a send the button rests a minute, so it isn't pressed into the throttle (6 per 10 minutes)
const sent = ref(false)
let sentTimer: ReturnType<typeof setTimeout> | undefined

async function resend() {
  if (sending.value || sent.value) return
  sending.value = true
  try {
    await auth.resendVerification()
    notification.success('Письмо отправлено. Проверьте и папку «Спам».')
    sent.value = true
    sentTimer = setTimeout(() => (sent.value = false), 60_000)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    sending.value = false
  }
}

onBeforeUnmount(() => clearTimeout(sentTimer))
</script>