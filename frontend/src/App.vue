<template>
  <RouterView />
  <ConfirmModal />
  <div v-if="notification.visible" class="toast" :class="{ error: notification.type === 'error' }" role="status">
    <span>{{ notification.message }}</span>
    <button v-if="notification.action" type="button" class="toast-action" @click="notification.runAction()">
      {{ notification.action.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import ConfirmModal from '@/shared/ui/ConfirmModal.vue'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

const notification = useNotificationStore()
</script>