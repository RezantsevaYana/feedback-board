<template>
  <BaseModal label="Вход для команды" narrow tag="form" novalidate @close="emit('close')" @submit.prevent="submit">
    <div class="drawer-top">
      <h2>Вход для команды</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <InputComponent
      v-model="email"
      label="Email"
      type="text"
      inputmode="email"
      autocapitalize="off"
      spellcheck="false"
      autocomplete="username"
      :errors="v$.email"
      validate-on-submit
      autofocus
    />
    <InputComponent
      v-model="password"
      label="Пароль"
      type="password"
      autocomplete="current-password"
      :errors="v$.password"
      validate-on-submit
    />
    <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">{{ sending ? 'Входим…' : 'Войти' }}</button>
    <p class="note">Для участников доски. Аккаунт появляется из приглашения или регистрации.</p>
  </BaseModal>
</template>

<script setup lang="ts">
import { inject, ref, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { email as emailRule, helpers, required } from '@vuelidate/validators'
import { ApiError } from '@/api/client'
import { ROLE_LABELS } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// «Вход для команды» (TZ 5.6): email + password, Sanctum session on our домен.
// Валидация своя через @vuelidate (нативную отключаем: novalidate на форме,
// type="text"+inputmode="email", никаких required). Паттерн как в kerrdental:
// клиентские правила + отдельное server-правило, в которое кладём сообщение бэка.
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

const email = ref('')
const password = ref('')
const sending = ref(false)
const emailServer = ref('')
const passwordServer = ref('')

const rules = {
  email: {
    required: helpers.withMessage('Введите email.', required),
    email: helpers.withMessage('Проверьте email: пропущена @ или домен.', emailRule),
    server: helpers.withMessage(
      () => emailServer.value,
      () => !emailServer.value,
    ),
  },
  password: {
    required: helpers.withMessage('Введите пароль.', required),
    server: helpers.withMessage(
      () => passwordServer.value,
      () => !passwordServer.value,
    ),
  },
}

const v$ = useVuelidate(rules, { email, password })

watch(email, () => (emailServer.value = ''))
watch(password, () => (passwordServer.value = ''))

async function submit() {
  if (sending.value) return
  emailServer.value = ''
  passwordServer.value = ''
  const isValid = await v$.value.$validate()
  if (!isValid) return

  sending.value = true
  try {
    await board.login(email.value.trim(), password.value)
    if (!board.team) {
      emailServer.value = board.role
        ? `Вы вошли. Ваша роль на этой доске — «${ROLE_LABELS[board.role]}», режим команды есть у менеджеров и админов.`
        : 'Вы вошли, но этот проект ведут другие админы.'
      v$.value.email.$touch()
      return
    }
    notification.success('Вы вошли — режим команды')
    close()
  } catch (e) {
    if (e instanceof ApiError) {
      emailServer.value = e.field('email') ?? ''
      passwordServer.value = e.field('password') ?? ''
      if (!emailServer.value && !passwordServer.value) passwordServer.value = e.message
    } else {
      passwordServer.value = String(e)
    }
    v$.value.$touch()
  } finally {
    sending.value = false
  }
}
</script>
