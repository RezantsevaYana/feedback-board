<template>
  <BaseModal label="Хиты недели" @close="emit('close')">
    <div class="drawer-top">
      <div class="weekly-modal-head">
        <span class="weekly-modal-crown" aria-hidden="true">🏆</span>
        <h2>Хиты недели</h2>
      </div>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <div class="weekly-modal-sub">
      <span>Ваши герои этой недели</span>
      <span v-if="rangeLabel" class="weekly-modal-range-chip">{{ rangeLabel }}</span>
    </div>
    <ul class="weekly-modal-list">
      <li
        v-for="(entry, i) in filled"
        :key="entry.category.id"
        class="weekly-modal-row"
        :data-color="entry.category.color ?? 'none'"
        :style="{ '--i': i }"
      >
        <CategoryTag :category="entry.category" />
        <div class="weekly-modal-body">
          <h3>{{ entry.post!.title }}</h3>
          <span class="who">{{ entry.post!.author_name || TEXT.anonymous }}</span>
        </div>
        <span class="weekly-modal-score" :data-dir="scoreDir(entry.post!)">{{ scoreLabel(entry.post!) }}</span>
      </li>
    </ul>
    <div class="weekly-modal-actions">
      <button type="button" class="btn btn-primary" @click="openSection()">Посмотреть всех</button>
      <button type="button" class="btn" @click="close()">Закрыть</button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, inject, onMounted } from 'vue'
import type { Post } from '@/api/types'
import { TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import { fireConfetti } from '@/shared/utils/confetti'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { score, useBoardStore } from '@/store/board/useBoardStore'

const emit = defineEmits<{
  close: []
  'open-section': []
}>()
const board = useBoardStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

// В модалке — только категории с победителем. Пустые опускаем,
// чтобы не создавать «ничего не случилось» ощущение при первом визите.
const filled = computed(() => board.weeklyWinners.filter((e) => e.post))

const rangeLabel = computed(() => {
  const meta = board.weeklyWinnersMeta
  if (!meta) return ''
  return `${fmtDate(meta.week_start)} — ${fmtDate(meta.week_end)}`
})

function scoreLabel(post: Post): string {
  const s = score(post)
  if (s > 0) return `+${s}`
  if (s < 0) return `${s}`
  return `${post.likes_count}`
}
function scoreDir(post: Post): 'up' | 'down' | 'zero' {
  const s = score(post)
  if (s > 0) return 'up'
  if (s < 0) return 'down'
  return 'zero'
}

function openSection() {
  emit('open-section')
  close()
}

// Салют на открытие — модалка выпадает один раз в неделю, повод настоящий;
// fireConfetti сам уважает prefers-reduced-motion.
onMounted(() => {
  if (filled.value.length) fireConfetti()
})
</script>
