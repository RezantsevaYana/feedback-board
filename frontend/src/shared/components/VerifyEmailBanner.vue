<template>
  <div class="verify-banner" role="status">
    <p class="verify-banner-text">
      <strong>Подтвердите почту, чтобы открыть доски для всех.</strong>
      Пока их видите только вы: ссылка и виджет заработают после подтверждения.
      Письмо со ссылкой ушло на <span class="verify-banner-email">{{ email }}</span>.
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
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

// Until the email is confirmed the owner's boards are closed to the public (EnsureProjectPublished on the API).
// Shown under AppHeader in the cabinet and on top of an unpublished board; the caller gives the container.
defineProps<{
  email: string
}>()

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