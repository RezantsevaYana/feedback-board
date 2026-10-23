<template>
  <AppHeader />
  <main class="container base-page boards-hub">
    <section v-if="auth.boardsLoading && !auth.boardsLoaded" class="boards-hub-loading">
      <p class="note">Загружаем…</p>
    </section>
    <section v-else-if="auth.boards.length === 0" class="boards-hub-empty">
      <h1>Привет, {{ auth.me?.name }}</h1>
      <p class="boards-hub-empty-lead">У вас пока нет досок.</p>
      <p class="note">Доска — это одно место, куда ваш продукт присылает баги и идеи от пользователей.</p>
      <RouterLink to="/app/boards/new" class="btn btn-primary">Создать первую доску</RouterLink>
    </section>
    <template v-else>
      <div class="boards-hub-top">
        <h1>Мои доски</h1>
        <RouterLink to="/app/boards/new" class="btn btn-primary">+ Новая доска</RouterLink>
      </div>
      <div class="boards-grid">
        <BoardCard v-for="b in auth.boards" :key="b.id" :board="b" />
      </div>
    </template>
    <TrashSection v-if="auth.boardsLoaded && auth.trashed.length" :boards="auth.trashed" />
  </main>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import BoardCard from './components/BoardCard.vue'
import TrashSection from './components/TrashSection.vue'

const auth = useAuthStore()

onMounted(async () => {
  if (!auth.boardsLoaded) await auth.fetchBoards()
})
</script>
