<template>
  <FoldSection id="search" title="Поисковики" class="board-access-group" :summary="summary">
    <!-- test.md п. 19 — show the board page in search engine results. Open boards only; closed ones never reach
         guests at all. On a phone the block folds, the header reads the current state. -->
    <label class="switch-row">
      <span>
        <b>Показывать в поисковиках</b>
        <small>
          Если выключить, на странице доски появится
          <code>&lt;meta name=&quot;robots&quot; content=&quot;noindex, nofollow&quot;&gt;</code>,
          а в robots.txt — <code>Disallow: /{{ slug }}</code>.
          Поисковики перестанут показывать доску; уже проиндексированная пропадёт из выдачи не сразу.
          По ссылке доска открывается как обычно.
        </small>
      </span>
      <SwitchComponent v-model="searchIndexable" aria-label="Показывать в поисковиках" :disabled="saving" />
    </label>
  </FoldSection>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import FoldSection from '@/shared/ui/FoldSection.vue'
import SwitchComponent from '@/shared/ui/SwitchComponent.vue'

// «Доступ» → «Поисковики» у открытой доски — один тумблер «Показывать в поисковиках» (test.md п. 19).
// Гостевые настройки (п. 40) живут в отдельном блоке «Гости» — визуально смыслы разные.
const props = defineProps<{
  slug: string
  searchIndexable: boolean
}>()
const emit = defineEmits<{
  saved: [searchIndexable: boolean]
}>()
const notification = useNotificationStore()

// local optimistic state: v-model flips at once, the server echo confirms or we revert
const searchIndexable = ref(props.searchIndexable)
const saving = ref(false)

// phone fold header: one line with the current rule
const summary = computed(() => (props.searchIndexable ? 'Видна в поисковиках' : 'Скрыта от поисковиков'))

watch(() => props.searchIndexable, (next) => {
  if (!saving.value) searchIndexable.value = next
})

// save on every flip (no «Сохранить»: a toggle is an action itself)
watch(searchIndexable, async (next, prev) => {
  if (next === prev || saving.value) return
  saving.value = true
  try {
    const res = await BoardsApi.update(props.slug, { search_indexable: next })
    emit('saved', res.data.search_indexable)
    notification.success(res.data.search_indexable ? 'Доска снова в поисковиках' : 'Доска скрыта от поисковиков')
  } catch (e) {
    // revert without re-triggering the watcher
    saving.value = false
    searchIndexable.value = prev
    notification.error(errorMessage(e))
    return
  }
  saving.value = false
})
</script>
