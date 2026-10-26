<template>
  <AppHeader />
  <main class="container base-page boards-hub">
    <section v-if="auth.boardsLoading && !auth.boardsLoaded" class="boards-hub-loading">
      <p class="note">Загружаем…</p>
    </section>
    <!-- first visit: a big greeting on the left, one line on what a board is for, one big button -->
    <section v-else-if="auth.boards.length === 0" class="boards-hub-hello">
      <h1>Привет, {{ auth.me?.name }}</h1>
      <p class="boards-hub-hello-lead">Создайте первую доску: сюда ваш продукт будет присылать баги и идеи.</p>
      <p class="note">Подключите виджет на сайт или просто поделитесь ссылкой на доску.</p>
      <!-- hover rolls the label into «Поехали →», same as «Предложить» on the board -->
      <RouterLink to="/app/boards/new" class="btn btn-primary" aria-label="Создать первую доску">
        <span class="cta-roll" aria-hidden="true"><span>Создать первую доску</span><span>Поехали →</span></span>
      </RouterLink>
    </section>
    <template v-else>
      <!-- on a phone the button leaves the head and pins to the bottom, like «Предложить» on the board;
           hover rolls the label into «Поехали →» (shared .cta-roll) -->
      <div class="boards-hub-top">
        <h1>Мои доски</h1>
        <RouterLink to="/app/boards/new" class="btn btn-primary boards-hub-new" aria-label="Новая доска">
          <span class="cta-roll" aria-hidden="true"><span>+ Новая доска</span><span>Поехали →</span></span>
        </RouterLink>
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
