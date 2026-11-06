<template>
  <aside class="onboarding-preview" :class="{ folded }" aria-label="Так будет выглядеть доска">
    <div class="onboarding-preview-cap">
      <span>Меняется: <b>{{ caption }}</b></span>
      <!-- phone: the strip shows only the changed part; the whole board on request -->
      <button type="button" class="link-btn onboarding-preview-toggle" :aria-expanded="!folded" @click="folded = !folded">{{ folded ? 'Вся доска' : 'Свернуть' }}</button>
    </div>

    <!-- phone, folded: only what this step changes -->
    <div class="onboarding-lens" aria-hidden="true">
      <template v-if="focus === 'name'">
        <span class="onboarding-mini-tag focus" :class="{ bump: flash }">{{ shownName }}</span>
        <span class="onboarding-mini-url focus" :class="{ bump: flash }">{{ url }}</span>
      </template>
      <template v-else-if="focus === 'vis'">
        <span class="onboarding-mini-tag">{{ shownName }}</span>
        <span class="onboarding-mini-vis focus" :class="{ bump: flash }">{{ visibilityLabel }}</span>
      </template>
      <span v-else-if="focus === 'cats'" class="onboarding-mini-filters focus" :class="{ bump: flash }">
        <i class="on">Все</i><i v-for="c in categories" :key="c.name">{{ c.name }}</i>
      </span>
      <ol v-else-if="focus === 'cols'" class="onboarding-lens-flow focus" :class="{ bump: flash }">
        <li v-for="s in statuses" :key="s.name"><span class="pill" :data-color="s.color ?? 'none'" :style="entityColorStyle(s.color)">{{ s.name }}</span></li>
      </ol>
      <template v-else-if="focus === 'card'">
        <div v-if="idea?.title" class="onboarding-mini-card focus" :class="{ bump: flash }">
          <span v-if="idea.category" class="type slant" :data-color="idea.category.color ?? 'none'" :style="entityColorStyle(idea.category.color)">{{ idea.category.name }}</span>
          <b>{{ idea.title }}</b>
        </div>
        <div v-else class="onboarding-mini-card onboarding-mini-slot focus">Ваше предложение появится здесь</div>
      </template>
      <template v-else-if="focus === 'where'">
        <span v-if="visibility === 'public'" class="onboarding-mini-url focus">{{ url }}</span>
        <span v-if="visibility === 'public'" class="onboarding-mini-launcher focus">Обратная связь</span>
        <span v-else class="onboarding-mini-people focus"><i>А</i><i>О</i><i>+</i> участники</span>
      </template>
    </div>

    <!-- the whole board in miniature; desktop always, phone when unfolded -->
    <div class="onboarding-mini" :class="{ focusing: !!focus }" aria-hidden="true">
      <div class="onboarding-mini-head">
        <span class="onboarding-mini-tag" data-part="name" :class="part('name')">{{ shownName }}</span>
        <span class="onboarding-mini-vis" data-part="vis" :class="part('vis')">{{ visibilityLabel }}</span>
      </div>
      <span class="onboarding-mini-url" data-part="url" :class="part(visibility === 'public' ? ['name', 'where'] : 'name')">{{ url }}</span>
      <span class="onboarding-mini-filters" data-part="cats" :class="part('cats')">
        <i class="on">Все</i><i v-for="c in categories" :key="c.name">{{ c.name }}</i>
      </span>
      <div class="onboarding-mini-cols" data-part="cols" :class="part('cols')">
        <div v-for="(s, i) in statuses" :key="s.name" class="onboarding-mini-col">
          <span class="pill" :data-color="s.color ?? 'none'" :style="entityColorStyle(s.color)">{{ s.name }}</span>
          <!-- step 6: the owner's card as it's typed, or the slot it will take -->
          <template v-if="i === 0 && focus === 'card'">
            <div v-if="idea?.title" class="onboarding-mini-card focus" :class="{ bump: flash }" data-part="card">
              <span v-if="idea.category" class="type slant" :data-color="idea.category.color ?? 'none'" :style="entityColorStyle(idea.category.color)">{{ idea.category.name }}</span>
              <b>{{ idea.title }}</b>
            </div>
            <div v-else class="onboarding-mini-card onboarding-mini-slot focus" data-part="card">Ваше предложение появится здесь</div>
          </template>
          <div v-else-if="i === 0 && categories[0]" class="onboarding-mini-card">
            <span class="type slant" data-part="tag" :class="part('cats')" :data-color="categories[0].color ?? 'none'" :style="entityColorStyle(categories[0].color)">{{ categories[0].name }}</span>
            <i /><i />
          </div>
        </div>
      </div>
      <span v-if="visibility === 'public'" class="onboarding-mini-launcher" data-part="where" :class="part('where')">Обратная связь</span>
      <span v-else class="onboarding-mini-people" data-part="where" :class="part('where')"><i>А</i><i>О</i><i>+</i> участники</span>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { BoardVisibility } from '@/api/boards'
import type { PaletteColor } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useMediaQuery } from '@/shared/composables/useMediaQuery'
import { entityColorStyle } from '@/shared/utils/entityColor'

// The board in miniature next to the onboarding step (TEST.md п. 20, prototype frontend/demo/onboarding-prototype.html).
// The part the current step changes is framed in red, the rest steps back, and the frame flashes on every change.
// On a phone it's a strip under the question with only that part; «Вся доска» unfolds the whole miniature.
// Before the board exists (step 1) it shows the defaults every new board gets.
const props = defineProps<{
  step: number
  name: string
  slug: string
  visibility: BoardVisibility
  // step 6: the first post being typed
  idea?: { title: string; category: { name: string; color: PaletteColor | null } | null }
}>()
const board = useBoardStore()

type Focus = 'name' | 'vis' | 'cats' | 'cols' | 'where' | 'card'
const FOCUS: Record<number, Focus> = { 1: 'name', 2: 'vis', 3: 'cats', 4: 'cols', 5: 'where', 6: 'card' }
const focus = computed<Focus | undefined>(() => FOCUS[props.step])

const DEFAULT_CATEGORIES: { name: string; color: PaletteColor | null }[] = [
  { name: 'Баг', color: 'red' },
  { name: 'Идея', color: 'blue' },
]
const DEFAULT_STATUSES: { name: string; color: PaletteColor | null }[] = [
  { name: 'Новое', color: 'blue' },
  { name: 'Готово', color: 'green' },
  { name: 'Отклонено', color: 'red' },
]
const ready = computed(() => !!props.slug && board.project?.slug === props.slug)
const categories = computed(() => (ready.value ? board.categories : DEFAULT_CATEGORIES))
const statuses = computed(() => (ready.value ? board.statuses : DEFAULT_STATUSES))

const shownName = computed(() => props.name || 'Название доски')
const url = computed(() => `${window.location.host}/${props.slug || '…'}`)
const visibilityLabel = computed(() => (props.visibility === 'public' ? 'Открытая' : 'Закрытая'))
const CAPTIONS: Record<Focus, string> = {
  name: 'название и адрес доски',
  vis: 'кто видит доску — метка у названия',
  cats: 'категории — фильтры и метки на карточках',
  cols: 'статусы — колонки доски',
  where: 'адрес доски и кнопка на вашем сайте',
  card: 'ваше предложение — первая карточка',
}
const caption = computed(() => {
  if (focus.value === 'where' && props.visibility === 'private') return 'участники доски'
  return focus.value ? CAPTIONS[focus.value] : ''
})

// a short flash on the framed part each time what it shows changes
const flash = ref(false)
let flashTimer: ReturnType<typeof setTimeout> | null = null
watch(
  () => JSON.stringify([props.name, props.slug, props.visibility, categories.value.map((c) => c.name), statuses.value.map((s) => s.name), props.idea]),
  () => {
    flash.value = false
    if (flashTimer) clearTimeout(flashTimer)
    requestAnimationFrame(() => (flash.value = true))
    flashTimer = setTimeout(() => (flash.value = false), 500)
  },
)
function part(name: Focus | Focus[]) {
  const on = Array.isArray(name) ? name.includes(focus.value as Focus) : name === focus.value
  return { focus: on, bump: on && flash.value }
}

// phone: folded to the strip by default; the toggle has no say on a wide screen
const phone = useMediaQuery('(max-width: 767px)')
const folded = ref(true)
watch(phone, (isPhone) => isPhone && (folded.value = true))
</script>
