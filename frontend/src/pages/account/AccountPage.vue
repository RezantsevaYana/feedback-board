<template>
  <div class="app-page">
    <div class="app-page-head">
      <h1>Настройки аккаунта</h1>
    </div>

    <section id="profile" class="board-settings-section">
      <h2 class="visually-hidden">Профиль</h2>
      <AccountProfileForm />
    </section>

    <section id="password" class="board-settings-section">
      <AccountPasswordForm />
    </section>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AccountPasswordForm from './components/AccountPasswordForm.vue'
import AccountProfileForm from './components/AccountProfileForm.vue'

// /app/account inside AppShell: «Профиль» and «Пароль» are sections under «Настройки аккаунта» in the side menu,
// next to the board's pages, so the account isn't a place of its own.
const route = useRoute()
// arriving from another page's menu link (…/account#password): land on that section
onMounted(async () => {
  if (!route.hash) return
  await nextTick()
  document.querySelector(route.hash)?.scrollIntoView()
})
</script>
