<template>
  <AppHeader />
  <main class="container base-page board-workspace-page">
    <div class="board-workspace">
      <nav class="settings-nav" aria-label="Разделы аккаунта">
        <a
          v-for="item in items"
          :key="item.hash"
          :href="item.hash"
          class="settings-nav-item"
          :aria-current="current === item.hash ? 'location' : undefined"
          @click.prevent="pick(item.hash)"
        >
          {{ item.label }}
        </a>
      </nav>
      <div class="board-workspace-main">
        <div class="page-head board-workspace-head">
          <h1>Настройки аккаунта</h1>
        </div>

        <section id="profile" class="board-settings-section">
          <h2>Профиль</h2>
          <AccountProfileForm />
        </section>

        <section id="password" class="board-settings-section">
          <h2>Пароль</h2>
          <AccountPasswordForm />
        </section>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppHeader from '@/shared/components/AppHeader.vue'
import AccountPasswordForm from './components/AccountPasswordForm.vue'
import AccountProfileForm from './components/AccountProfileForm.vue'

// /app/account: the same frame as a board's settings (side nav, cards on the right), sections with anchors.
// Two short sections both fit on one screen, so the nav marks the one picked, without following the scroll.
const route = useRoute()
const router = useRouter()

const items = [
  { hash: '#profile', label: 'Профиль' },
  { hash: '#password', label: 'Пароль' },
]
const current = ref(items.some((i) => i.hash === route.hash) ? route.hash : '#profile')

function pick(hash: string) {
  current.value = hash
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  document.querySelector(hash)?.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' })
  void router.replace({ hash })
}
</script>