<template>
  <MenuComponent :items="items" :caption="auth.me?.email" class="account-menu" aria-label="Меню аккаунта">
    <span class="account-menu-name">{{ auth.me?.name }}</span>
    <span class="account-menu-initials" aria-hidden="true">{{ initials }}</span>
    <AppIcon name="chevron" />
  </MenuComponent>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'

// The right side of the /app/* header: the name opens the account's places and the way out.
// On a phone the name gives way to initials, the header row is already taken by the board switcher.
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const loggingOut = ref(false)

const initials = computed(() => {
  const words = (auth.me?.name ?? '').trim().split(/\s+/).filter(Boolean)
  return words.slice(0, 2).map((w) => w[0]!.toUpperCase()).join('') || (auth.me?.email[0] ?? '?').toUpperCase()
})

const items = computed<MenuItem[]>(() => [
  { label: 'Настройки аккаунта', current: route.name === 'account', run: () => router.push('/app/account') },
  { label: 'Мои доски', current: route.name === 'hub', run: () => router.push('/app/boards') },
  { label: 'Выйти', separated: true, disabled: loggingOut.value, run: logout },
])

async function logout() {
  if (loggingOut.value) return
  loggingOut.value = true
  try {
    await auth.logout()
    router.push('/app/login')
  } finally {
    loggingOut.value = false
  }
}
</script>