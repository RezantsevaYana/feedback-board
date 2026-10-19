<template>
  <BaseModal label="Вход для команды" narrow tag="form" @close="emit('close')" @submit.prevent="submit">
    <div class="drawer-top">
      <h2>Вход для команды</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
    </div>
    <InputComponent v-model="email" label="Email" type="email" autocomplete="username" required autofocus />
    <InputComponent v-model="password" label="Пароль" :error="error" type="password" autocomplete="current-password" required />
    <button class="btn btn-primary" type="submit" :disabled="sending">{{ sending ? 'Входим…' : 'Войти' }}</button>
    <p class="note">Только для тех, кто разбирает предложения. Аккаунты заводит разработчик, у каждого свой пароль.</p>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// «Вход для команды» (TZ 5.6): email + password, Sanctum session on our domain.
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()

const email = ref('')
const password = ref('')
const error = ref('')
const sending = ref(false)

async function submit() {
  error.value = ''
  sending.value = true
  try {
    await board.login(email.value.trim(), password.value)
    if (!board.team) {
      error.value = 'Вы вошли, но этот проект ведут другие админы.'
      return
    }
    notification.success('Вы вошли — режим команды')
    emit('close')
  } catch (e) {
    error.value = e instanceof ApiError ? (e.field('email') ?? e.field('password') ?? e.message) : String(e)
  } finally {
    sending.value = false
  }
}
</script>
