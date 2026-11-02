<template>
  <!-- without the side menu (the boards list) «Выйти» goes back into the account menu in the header -->
  <AppHeader :menu="!noSidebar" @menu="menuOpen = true" />

  <main v-if="noSidebar" class="container base-page">
    <RouterView />
  </main>
  <main v-else class="container base-page app-layout">
    <aside class="app-layout-side">
      <AppSidebar />
    </aside>
    <div class="app-layout-main">
      <RouterView />
    </div>
  </main>

  <NavDrawer v-if="menuOpen" label="Меню кабинета" anchor=".app-shell-menu" @close="menuOpen = false">
    <AppSidebar />
  </NavDrawer>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppHeader from '@/shared/components/AppHeader.vue'
import AppSidebar from '@/shared/components/AppSidebar.vue'
import NavDrawer from '@/shared/ui/NavDrawer.vue'

// Frame of every signed-in /app/* page, a parent route: the header on top (board switcher, account), the side menu
// on the left (AppSidebar: the board's pages with their sections, «Выйти» and the theme at the bottom), the page on
// the right. It stays mounted while the pages change, so the menu's nested sections fold in and out smoothly.
// «Мои доски» (meta.noSidebar) is the whole page without the menu.
const route = useRoute()
const menuOpen = ref(false)
const noSidebar = computed(() => Boolean(route.meta.noSidebar))
</script>