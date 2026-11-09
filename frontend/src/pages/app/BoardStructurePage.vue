<template>
  <BoardWorkspace :slug="slug" page="structure">
    <p v-if="loading" class="note">Загружаем…</p>

    <section v-else-if="allowed" class="board-settings-section structure-settings">
      <!-- Shared card, stacked groups; compact screens show the tags / status chain while folded. -->
      <FoldSection id="categories" title="Категории" class="structure-section" hint-aria-label="Где видны категории на доске">
        <template #hint>
          <span class="hint-cap">На доске — метка на карточке и фильтр</span>
          <span class="hint-mini-filters"><i class="on">Все</i><i>Баг</i><i>Идея</i></span>
          <span class="hint-mini-card">
            <span class="hint-tag hl" data-color="red">Баг</span>
            <b>Не прикрепляется файл больше 20 МБ</b>
            <em></em>
          </span>
        </template>
        <template #summary>
          <CategoryTag v-for="c in board.categories" :key="c.id" :category="c" />
        </template>
        <CategoriesEditor v-if="projectReady" settings-layout />
        <p v-else class="note">{{ board.loadError || 'Загружаем…' }}</p>
      </FoldSection>

      <FoldSection
        id="statuses"
        title="Статусы"
        class="structure-section"
        hint-aria-label="Где видны статусы на доске"
        :summary="board.statuses.map((s) => s.name).join(' → ')"
      >
        <template #hint>
          <span class="hint-cap">На доске — колонки канбана и метка в списке</span>
          <span class="hint-mini-kanban">
            <i><span class="hint-pill hl" data-color="none">Новое</span><em></em><em></em></i>
            <i><span class="hint-pill hl" data-color="green">В работе</span><em></em></i>
            <i><span class="hint-pill hl" data-color="green">Готово</span><em></em></i>
          </span>
        </template>
        <StatusesEditor v-if="projectReady" settings-layout />
        <p v-else class="note">{{ board.loadError || 'Загружаем…' }}</p>
      </FoldSection>
    </section>

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

<style lang="scss">
@use '@/scss/utils' as *;

.structure-settings {
  min-width: 0;
  max-width: 760px;
  gap: 14px;
  .structure-section { min-width: 0; }
  .structure-section + .structure-section {
    padding-top: 14px;
    border-top: 1px solid var(--line);
  }
  .structure-section:not(.foldable) { display: grid; gap: 10px; }
  .fold-section-title { font-size: 15px; }
  .fold-section-body { gap: 10px; }
  .note { margin: 0; }
  .cat-list { grid-template-columns: minmax(0, 1fr); }
  .cat-list > li > .cat-row:not(form) { padding: 7px 10px; }
  .cat-list > li > .cat-edit {
    margin: 0;
    padding: 10px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: color-mix(in srgb, var(--accent) 5%, var(--surface));
  }
  .cat-row > .field { margin: 0; }
  .cat-add { margin-top: 0; }
  .cat-add > .cat-row {
    flex-wrap: nowrap;
    > .field { flex: 1 1 0; min-width: 0; }
    > .entity-color { order: 0; flex: none; }
    > .btn { flex: none; }
  }
  .entity-color-compact {
    display: block;
    .entity-color-trigger { width: var(--settings-control-size, 40px); padding: 0; }
    .entity-color-trigger .entity-color-text,
    .entity-color-trigger > .ico { display: none; }
  }
  .cat-add-icon { display: none; }

  @media screen and (max-width: $mobile) {
    gap: 0;
    .structure-section + .structure-section {
      @include bleed;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--line);
    }
    .fold-section-title { font-size: 17px; }
    .fold-section-body { gap: 8px; }
    .cat-list {
      gap: 0;
      margin: 0;
      padding: 0;
      border: 1px solid var(--line);
      border-radius: 12px;
    }
    .cat-list > li {
      border-bottom: 1px solid var(--line);
      &:first-child { border-radius: 11px 11px 0 0; }
      &:last-child { border-radius: 0 0 11px 11px; border-bottom: 0; }
      > .cat-row:not(form) {
        min-height: var(--settings-control-size, 44px);
        padding: 4px 8px 4px 12px;
        border: 0;
        border-radius: inherit;
        flex-wrap: nowrap;
        > .type, > .pill { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        > .cat-more, > .cat-grip, > .chip { flex: none; }
      }
      > .cat-edit { padding: 10px 12px 12px; border: 0; border-radius: inherit; }
      &.note { padding: 12px; }
    }
    .cat-add .input { min-height: var(--settings-control-size, 44px); font-size: 16px; }
    .cat-add > .cat-row > .btn,
    .cat-add .entity-color-trigger { width: var(--settings-control-size, 44px); height: var(--settings-control-size, 44px); padding: 0; }
    .cat-add-word { display: none; }
    .cat-add-icon { display: inline; font-size: 18px; }
  }
}
</style>
