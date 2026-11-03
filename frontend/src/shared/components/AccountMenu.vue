<template>
  <MenuComponent :items="items" :caption="auth.me?.email" class="account-menu" aria-label="Меню аккаунта">
    <span class="account-menu-name">{{ auth.me?.name }}</span>
    <span class="account-menu-initials" aria-hidden="true">{{ initials }}</span>
    <AppIcon name="chevron" />
  </MenuComponent>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useLogout } from '@/shared/composables/useLogout'
import { initials as toInitials } from '@/shared/utils/format'
import AppIcon from '@/shared/ui/AppIcon.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'

// The right side of the /app/* header: the name opens the account's places. «Выйти» is here only on the pages
// around the cabinet (login, invitation…); inside it the way out sits at the bottom of the side menu.
// On a phone the name gives way to initials, the header row is already taken by the board switcher.
const props = defineProps<{
  withLogout?: boolean
}>()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { logout, busy } = useLogout()

const initials = computed(() => toInitials(auth.me?.name, auth.me?.email))

const items = computed<MenuItem[]>(() => [
  { label: 'Настройки аккаунта', current: route.name === 'account', run: () => router.push('/app/account') },
  { label: 'Мои доски', current: route.name === 'hub', run: () => router.push('/app/boards') },
  // «Админка сервиса» лежит на Orchid (/admin, не SPA): полная перезагрузка, не router.push.
  ...(auth.me?.is_superadmin
    ? [{ label: 'Админка сервиса', run: () => { window.location.href = '/admin' } }]
    : []),
  ...(props.withLogout ? [{ label: 'Выйти', separated: true, disabled: busy.value, run: logout }] : []),
])
</script>
