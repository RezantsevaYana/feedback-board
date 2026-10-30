<template>
  <section v-if="sentTo" class="auth-form access-gate" aria-live="polite">
    <h1>Заявка отправлена</h1>
    <p class="auth-intro">
      Ответ придёт на <strong>{{ sentTo }}</strong>, когда команда доски её рассмотрит. До одобрения содержимое доски скрыто.
    </p>
    <p class="auth-switch">
      <RouterLink v-if="me" to="/app/boards">← Мои доски</RouterLink>
      <RouterLink v-else :to="{ path: '/app/login', query: { next: route.fullPath } }">Войти</RouterLink>
    </p>
  </section>

  <form v-else class="auth-form access-gate" novalidate @submit.prevent="submit">
    <h1>{{ name ? `«${name}» — закрытая доска` : 'Это закрытая доска' }}</h1>
    <p class="auth-intro">
      Её видят только участники. Запросите доступ: команда доски рассмотрит заявку и ответит на почту.
    </p>
    <p v-if="me" class="access-gate-who">
      Запрос уйдёт от <strong>{{ me.name }}</strong> · {{ me.email }}
    </p>
    <template v-else>
      <InputComponent
        v-model="form.name"
        label="Имя"
        type="text"
        autocomplete="name"
        maxlength="80"
        :error="errors.name"
        @input="errors.name = ''"
      />
      <InputComponent
        v-model="form.email"
        label="Почта"
        hint="на неё придёт ответ"
        type="text"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        autocomplete="email"
        maxlength="160"
        :error="errors.email"
        @input="errors.email = ''"
      />
    </template>
    <TextAreaComponent
      v-model="form.message"
      label="Зачем нужен доступ"
      hint="по желанию, так заявку проще одобрить"
      rows="3"
      maxlength="1000"
      :error="errors.message"
      @input="errors.message = ''"
    />
    <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">
      {{ sending ? 'Отправляем…' : 'Запросить доступ' }}
    </button>
    <p class="auth-switch">
      <template v-if="me">
        Не тот аккаунт? <button type="button" class="link-btn" @click="emit('logout')">Выйти</button> · <RouterLink to="/app/boards">Мои доски</RouterLink>
      </template>
      <template v-else>
        Уже участник? <RouterLink :to="{ path: '/app/login', query: { next: route.fullPath } }">Войти</RouterLink>
      </template>
    </p>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { AccessRequestsApi } from '@/api/accessRequests'
import { ApiError, errorMessage } from '@/api/client'
import type { Me } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// The gate of a private board for a guest and for a logged-in outsider (docs/ROLES_PLAN.md, stage 3).
// A guest gives a name and an email; a logged-in visitor asks as their account, the server takes both from it.
// After sending, a short «ждите письма» screen; the board's content never shows here.
const props = defineProps<{
  slug: string
  /** The board's name from the 403; empty if the server didn't send it. */
  name: string
  me: Me | null
}>()
const emit = defineEmits<{
  logout: []
}>()
const route = useRoute()
const notification = useNotificationStore()

const form = reactive({ name: '', email: '', message: '' })
const errors = reactive({ name: '', email: '', message: '' })
const sending = ref(false)
const sentTo = ref('')

async function submit() {
  if (sending.value) return
  if (!props.me) {
    errors.name = form.name.trim() ? '' : 'Представьтесь — команде доски нужно понять, кто просит доступ.'
    errors.email = /^\S+@\S+\.\S+$/.test(form.email.trim()) ? '' : 'Введите почту в формате name@example.com: на неё придёт ответ.'
    if (errors.name || errors.email) return
  }
  sending.value = true
  try {
    const body = props.me
      ? { message: form.message.trim() }
      : { name: form.name.trim(), email: form.email.trim(), message: form.message.trim() }
    const res = await AccessRequestsApi.create(props.slug, body)
    sentTo.value = res.data.email
  } catch (e) {
    if (e instanceof ApiError && Object.keys(e.errors).length) {
      errors.name = e.field('name') ?? ''
      errors.email = e.field('email') ?? ''
      errors.message = e.field('message') ?? ''
    } else {
      notification.error(errorMessage(e))
    }
  } finally {
    sending.value = false
  }
}
</script>