<template>
  <main class="container base-page">
    <div v-if="board.notFound" class="center-page">
      <div>
        <h1>Доска не найдена</h1>
        <p class="note">Проверьте адрес: доска открывается по ссылке вида /название-проекта.</p>
      </div>
    </div>

    <div v-else-if="!board.project" class="center-page">
      <p class="note">{{ board.loadError || 'Загружаем доску…' }}</p>
    </div>

    <div v-else class="board-layout">
      <nav class="board-nav" aria-label="Разделы доски">
        <RouterLink class="proj slant" :to="{ name: 'board', params: { slug } }">{{ board.project.name }}</RouterLink>
        <button type="button" class="nav-item" :aria-current="board.view.section === 'posts' ? 'page' : undefined" @click="setSection('posts')">
          <AppIcon name="columns" /><span>Предложения</span>
        </button>
        <button type="button" class="nav-item" :aria-current="board.view.section === 'top' ? 'page' : undefined" @click="setSection('top')">
          <AppIcon name="chart" /><span>Топ</span>
        </button>
        <button type="button" class="btn btn-primary" @click="composing = true">Предложить</button>
        <div v-if="board.team" class="team-box">
          <span class="chip">режим команды</span>
          <span class="who-mail">{{ board.me?.email }}</span>
          <button type="button" class="link-btn" @click="editingCategories = true">Категории</button>
          <button type="button" class="link-btn" @click="board.logout()">Выйти</button>
        </div>
        <button v-else type="button" class="link-btn team-login" @click="loggingIn = true">Вход для команды</button>
      </nav>

      <div class="board-main">
        <TopPosts v-if="board.view.section === 'top'" @open="openPost" />
        <template v-else>
          <div class="board-head">
            <h1>Предложения</h1>
            <p v-if="board.team">Режим команды: перетащите карточку в «Готово» или «Отклонено» — доска попросит ответ для автора. Клик по карточке — редактирование.</p>
            <p v-else>Что предлагают и что уже сделано. Голосуйте: 👍 — нужно, 💩 — не нужно.</p>
          </div>

          <div class="filters">
            <div class="seg-icons" role="group" aria-label="Вид">
              <button type="button" :aria-pressed="board.view.layout === 'kanban'" @click="board.view.layout = 'kanban'"><AppIcon name="columns" />Колонки</button>
              <button type="button" :aria-pressed="board.view.layout === 'list'" @click="board.view.layout = 'list'"><AppIcon name="list" />Список</button>
            </div>
            <span class="toolbar-sep" aria-hidden="true" />
            <CategoryChips />
            <SelectComponent v-if="board.view.layout === 'list'" v-model="board.view.status" :options="statusOptions" aria-label="Статус" />
            <template v-if="board.team">
              <SelectComponent v-model="board.view.priority" :options="priorityOptions" aria-label="Приоритет" />
              <CheckboxComponent v-model="board.view.showHidden" label="Скрытые" />
            </template>
            <span class="spacer" />
            <div class="chips">
              <button type="button" :aria-pressed="board.view.sort === 'top'" @click="board.view.sort = 'top'">Популярные</button>
              <button type="button" :aria-pressed="board.view.sort === 'new'" @click="board.view.sort = 'new'">Новые</button>
              <button v-if="board.team" type="button" :aria-pressed="board.view.sort === 'priority'" @click="board.view.sort = 'priority'">Приоритет</button>
            </div>
          </div>

          <div v-if="board.loadError" class="alert" role="alert">{{ board.loadError }}</div>
          <KanbanBoard v-else-if="board.view.layout === 'kanban'" @open="openPost" />
          <PostList v-else @open="openPost" />
        </template>
      </div>
    </div>

    <PostModal v-if="board.project && postId" :id="postId" @close="closePost" />
    <SubmitModal v-if="composing && board.project" :project="board.project.slug" :categories="board.categories" @close="composing = false" @sent="onSent" />
    <LoginModal v-if="loggingIn" @close="loggingIn = false" />
    <CategoriesModal v-if="editingCategories && board.team" @close="editingCategories = false" />
  </main>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Post } from '@/api/types'
import { PRIORITIES, PRIORITY, STATUS, STATUSES } from '@/shared/constants/dict'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { type Section, useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import CheckboxComponent from '@/shared/ui/CheckboxComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import CategoriesModal from './components/CategoriesModal.vue'
import CategoryChips from './components/CategoryChips.vue'
import KanbanBoard from './components/KanbanBoard.vue'
import LoginModal from './components/LoginModal.vue'
import PostList from './components/PostList.vue'
import PostModal from './components/PostModal.vue'
import SubmitModal from './components/SubmitModal.vue'
import TopPosts from './components/TopPosts.vue'

// /<slug> and /<slug>/posts/<id>: the board with the post card on top (TZ 5.2).
const route = useRoute()
const router = useRouter()
const board = useBoardStore()
const notification = useNotificationStore()

const slug = computed(() => String(route.params.slug ?? ''))
const postId = computed(() => (route.params.id ? Number(route.params.id) : null))
const composing = ref(false)
const loggingIn = ref(false)
const editingCategories = ref(false)

const statusOptions = [
  { value: '' as const, label: 'Все статусы' },
  ...STATUSES.map((s) => ({ value: s, label: STATUS[s] })),
]
const priorityOptions = [
  { value: '' as const, label: 'Любой приоритет' },
  { value: 'none' as const, label: 'Без приоритета' },
  ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p] })),
]

watch(slug, (s) => board.open(s), { immediate: true })
watch(
  () => board.project?.name,
  (name) => (document.title = name ? `${name} · предложения` : 'Feedback Board'),
)
// any change of what is shown reloads from the server; team mode switches endpoints
watch(
  () => [board.view.section, board.view.layout, board.view.category, board.view.status, board.view.sort, board.view.priority, board.view.showHidden, board.team],
  () => board.project && board.refresh(),
)

function openPost(post: Post) {
  router.push({ name: 'post', params: { slug: slug.value, id: post.id } })
}
function closePost() {
  router.push({ name: 'board', params: { slug: slug.value } })
}
function onSent(post: Post) {
  composing.value = false
  notification.success('Предложение опубликовано')
  board.refresh()
  openPost(post)
}
function setSection(section: Section) {
  board.view.section = section
}
</script>
