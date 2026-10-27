<template>
  <AppHeader />
  <main class="container base-page auth-page invite-accept-page">
    <div v-if="loading" class="invite-accept-card">
      <p class="note">Загружаем приглашение…</p>
    </div>

    <div v-else-if="loadError" class="invite-accept-card">
      <h1>Ссылка не действует</h1>
      <p>{{ loadError }}</p>
      <p class="note">Если вы ждёте приглашение — попросите, чтобы его прислали ещё раз.</p>
      <RouterLink to="/" class="btn">На главную</RouterLink>
    </div>

    <div v-else-if="preview" class="invite-accept-card">
      <h1>Присоединиться к доске «{{ preview.board.name }}»</h1>
      <p class="auth-intro">
        <template v-if="preview.invited_by.name">
          {{ preview.invited_by.name }} зовёт вас в рабочую доску команды — место для идей, багов, запросов и обсуждений.
        </template>
        <template v-else>
          Вас зовут в рабочую доску команды — место для идей, багов, запросов и обсуждений.
        </template>
      </p>

      <dl class="invite-accept-meta">
        <div><dt>Почта</dt><dd>{{ preview.email }}</dd></div>
        <div><dt>Роль</dt><dd>{{ roleLabel(preview.role) }}</dd></div>
        <div><dt>Статус</dt><dd>{{ preview.is_team_member ? 'команда' : 'внешний' }}</dd></div>
      </dl>

      <!-- Залогинен и email совпал — одна кнопка. -->
      <template v-if="scenario === 'current-user-matches'">
        <p class="note">Вы уже вошли как <strong>{{ auth.me?.email }}</strong>.</p>
        <button type="button" class="btn btn-primary" :disabled="sending" :aria-busy="sending" @click="accept">
          {{ sending ? 'Присоединяемся…' : 'Присоединиться' }}
        </button>
      </template>

      <!-- Аккаунт существует, не залогинен — предлагаем войти. -->
      <template v-else-if="scenario === 'account-exists'">
        <p>На почту <strong>{{ preview.email }}</strong> уже есть аккаунт. Войдите, чтобы принять приглашение.</p>
        <RouterLink :to="{ path: '/app/login', query: { next: $route.fullPath, email: preview.email } }" class="btn btn-primary">
          Войти и принять
        </RouterLink>
      </template>

      <!-- Новый аккаунт — имя обязательно, пароль по желанию. -->
      <form v-else class="auth-form" novalidate @submit.prevent="accept">
        <InputComponent
          v-model="name"
          label="Как к вам обращаться"
          type="text"
          maxlength="80"
          autocomplete="name"
          :error="nameError"
          autofocus
        />
        <InputComponent
          v-model="password"
          label="Пароль"
          hint="можно пропустить — потом войдёте по ссылке"
          type="password"
          autocomplete="new-password"
          :error="passwordError"
        />
        <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending">
          {{ sending ? 'Присоединяемся…' : 'Присоединиться' }}
        </button>
      </form>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { InviteAcceptApi, type InvitationPreview } from '@/api/participants'
import { ApiError, errorMessage } from '@/api/client'
import { ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Public page (/app/invite/:token). The token is the credential — no auth required to read it.
// Three UI branches, computed from the server's `account_exists` flag + the current user:
//   current-user-matches → one button "Присоединиться"
//   account-exists       → "войдите, чтобы принять" (redirects to /app/login with next=…)
//   new-user             → name (required) + password (optional) → create + attach + session
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const notification = useNotificationStore()

const token = computed(() => String(route.params.token ?? ''))

const preview = ref<InvitationPreview | null>(null)
const loading = ref(true)
const loadError = ref('')

const name = ref('')
const password = ref('')
const nameError = ref('')
const passwordError = ref('')
const sending = ref(false)

const scenario = computed<'current-user-matches' | 'account-exists' | 'new-user'>(() => {
  if (!preview.value) return 'new-user'
  if (auth.me && auth.me.email.toLowerCase() === preview.value.email.toLowerCase()) {
    return 'current-user-matches'
  }
  if (preview.value.account_exists) return 'account-exists'
  return 'new-user'
})

onMounted(async () => {
  // Знаем ли мы, кто сейчас открыл страницу? Нужно для сценария current-user-matches.
  await auth.fetchMe().catch(() => undefined)
  try {
    const res = await InviteAcceptApi.show(token.value)
    preview.value = res.data
  } catch (e) {
    loadError.value = e instanceof ApiError
      ? e.message
      : errorMessage(e)
  } finally {
    loading.value = false
  }
})

function roleLabel(role: ProjectRole) {
  return ROLE_LABELS[role]
}

async function accept() {
  if (sending.value || !preview.value) return
  nameError.value = ''
  passwordError.value = ''

  const body: { name?: string; password?: string } = {}
  if (scenario.value === 'new-user') {
    const trimmed = name.value.trim()
    if (!trimmed) {
      nameError.value = 'Введите имя.'
      return
    }
    body.name = trimmed
    if (password.value) body.password = password.value
  }

  sending.value = true
  try {
    const res = await InviteAcceptApi.accept(token.value, body)
    notification.success(`Вы в доске «${res.data.board.name}»`)
    // The server set the session cookie on success — refetch /me so the hub sees the new membership.
    await auth.fetchMe(true).catch(() => undefined)
    router.push(`/${res.data.board.slug}`)
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.status === 409 && (e as unknown as { data?: unknown })) {
        notification.error(e.message)
        return
      }
      nameError.value = e.field('name') ?? ''
      passwordError.value = e.field('password') ?? ''
      if (!nameError.value && !passwordError.value) notification.error(e.message)
    } else {
      notification.error(errorMessage(e))
    }
  } finally {
    sending.value = false
  }
}
</script>
