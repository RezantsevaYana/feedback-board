<template>
  <BoardWorkspace :slug="slug" page="structure">
    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="allowed">
      <!-- on a phone both fold; folded, they show the tags / the chain of columns themselves -->
      <FoldSection id="categories" title="Категории" class="board-settings-section">
        <template #summary>
          <CategoryTag v-for="c in board.categories" :key="c.id" :category="c" />
        </template>
        <CategoriesEditor v-if="projectReady" />
        <p v-else class="note">{{ board.loadError || 'Загружаем…' }}</p>
      </FoldSection>

      <FoldSection id="statuses" title="Статусы" class="board-settings-section" :summary="board.statuses.map((s) => s.name).join(' → ')">
        <StatusesEditor v-if="projectReady" />
        <p v-else class="note">{{ board.loadError || 'Загружаем…' }}</p>
      </FoldSection>
    </template>

    <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>
  </BoardWorkspace>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { BoardsApi } from '@/api/boards'
import { useBoardStore } from '@/store/board/useBoardStore'
import CategoriesEditor from '@/shared/components/CategoriesEditor.vue'
import CategoryTag from '@/shared/components/CategoryTag.vue'
import StatusesEditor from '@/shared/components/StatusesEditor.vue'
import FoldSection from '@/shared/ui/FoldSection.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import { boardLoadError } from './composables/boardLoadError'
import { NO_CONFIGURE_TEXT, useLostAccess } from './composables/useLostAccess'

// «Категории и статусы» — how posts are sorted on the board (TEST.md п. 42): the board's editors, one under the
// other (the colour form of a row needs the width). They work through the board store, so it opens this project
// the same way BoardPage does; the settings request only checks that the user may configure the board.
const route = useRoute()
const board = useBoardStore()

const slug = computed(() => String(route.params.slug ?? ''))
const allowed = ref(false)
const loading = ref(true)
const loadError = ref('')
const projectReady = computed(() => board.project?.slug === slug.value)

watch(slug, load, { immediate: true })
useLostAccess(slug, () => {
  allowed.value = false
  loadError.value = NO_CONFIGURE_TEXT
})

async function load() {
  loading.value = true
  loadError.value = ''
  allowed.value = false
  try {
    await BoardsApi.show(slug.value)
    allowed.value = true
    void board.open(slug.value)
  } catch (e) {
    loadError.value = boardLoadError(e)
  } finally {
    loading.value = false
  }
  // /structure#statuses (and the old /settings#statuses): scroll to the section once it's there
  if (allowed.value && route.hash) {
    await nextTick()
    document.querySelector(route.hash)?.scrollIntoView()
  }
}
</script>