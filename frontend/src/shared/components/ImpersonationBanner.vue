<template>
  <div v-if="auth.me?.impersonating_by" class="impersonation-banner" role="alert">
    <div class="impersonation-banner-text">
      <strong>Админ сервиса вошёл как вы</strong>
      <span>Сейчас эту сессию держит {{ auth.me.impersonating_by.email }} — для разбора заявки в поддержку.</span>
    </div>
    <button type="button" class="impersonation-banner-stop" :disabled="busy" @click="stop">
      Вернуться в админку
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { AuthApi } from '@/api/auth'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { errorMessage } from '@/api/client'

// Appears above every SPA screen (mounted in App.vue) while a service admin is signed in as the current user.
// «Вернуться» завершает impersonation session и возвращает физически на /admin — Orchid сессия всё это время жила.
const auth = useAuthStore()
const notify = useNotificationStore()
const busy = ref(false)

async function stop() {
  if (busy.value) return
  busy.value = true
  try {
    const { data } = await AuthApi.stopImpersonation()
    window.location.href = data.redirect
  } catch (e) {
    notify.error(errorMessage(e))
    busy.value = false
  }
}
</script>
