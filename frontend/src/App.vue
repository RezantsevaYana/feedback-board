<template>
  <RouterView />
  <ConfirmModal />
  <div v-if="notification.visible" class="toast" :class="{ error: notification.type === 'error' }" role="status">{{ notification.message }}</div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import ConfirmModal from '@/shared/ui/ConfirmModal.vue'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

const notification = useNotificationStore()
</script>
