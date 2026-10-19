<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '@/api/client'
import { useToast } from '@/lib/toast'
import { useBoardStore } from '@/stores/board'
import AppIcon from './AppIcon.vue'
import BaseModal from './BaseModal.vue'

// «Вход для команды» (TZ 5.6): email + password, Sanctum session on our domain.
const emit = defineEmits<{ close: [] }>()
const board = useBoardStore()
const toast = useToast()

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
    toast.show('Вы вошли — режим команды')
    emit('close')
  } catch (e) {
    error.value = e instanceof ApiError ? (e.field('email') ?? e.field('password') ?? e.message) : String(e)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <BaseModal label="Вход для команды" narrow tag="form" @close="emit('close')" @submit.prevent="submit">
    <div class="drawer-top">
      <h2>Вход для команды</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
    </div>
    <label class="field">
      <span>Email</span>
      <input v-model="email" class="input" type="email" autocomplete="username" required autofocus />
    </label>
    <label class="field">
      <span>Пароль</span>
      <input v-model="password" class="input" :class="{ invalid: error }" type="password" autocomplete="current-password" required />
      <span v-if="error" class="err" role="alert">{{ error }}</span>
    </label>
    <button class="btn btn-primary" type="submit" :disabled="sending">{{ sending ? 'Входим…' : 'Войти' }}</button>
    <p class="note">Только для тех, кто разбирает предложения. Аккаунты заводит разработчик, у каждого свой пароль.</p>
  </BaseModal>
</template>