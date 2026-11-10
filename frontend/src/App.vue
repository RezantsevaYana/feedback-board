<template>
  <ImpersonationBanner />
  <RouterView />
  <footer v-if="route.name !== 'home'" class="container legal-footer" :class="{ 'legal-footer-board': route.name === 'board' }">
    <ServiceFeedback />
    <LegalLinks />
  </footer>
  <ThemeToggle v-if="isAuthRoute" class="auth-theme-toggle" />
  <ConfirmModal />
  <AuthChangedModal />
  <div v-if="notification.visible" class="toast" :class="{ error: notification.type === 'error' }" role="status">
    <AppIcon :name="notification.type === 'error' ? 'alert' : 'check'" :size="18" class="toast-icon" />
    <span class="toast-message">{{ notification.message }}</span>
    <button v-if="notification.action" type="button" class="toast-action" @click="notification.runAction()">
      {{ notification.action.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppIcon from '@/shared/ui/AppIcon.vue'
import LegalLinks from '@/shared/components/LegalLinks.vue'
import ServiceFeedback from '@/shared/components/ServiceFeedback.vue'
import ConfirmModal from '@/shared/ui/ConfirmModal.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'
import AuthChangedModal from '@/shared/components/AuthChangedModal.vue'
import ImpersonationBanner from '@/shared/components/ImpersonationBanner.vue'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useRoleSync } from '@/shared/composables/useRoleSync'

const notification = useNotificationStore()
useRoleSync()

// На auth-экранах нет board-nav, где обычно живёт переключатель, поэтому вешаем плавающий в углу.
const AUTH_ROUTES = new Set(['register', 'login', 'forgot-password', 'reset-password', 'verify-email', 'invite-accept'])
const route = useRoute()
const isAuthRoute = computed(() => AUTH_ROUTES.has(String(route.name ?? '')))
</script>
