<template>
  <div class="onboarding-step">
    <h1>О чём будут предложения?</h1>
    <p class="onboarding-lead">Категории помогают разбирать поток: пользователь выбирает одну в форме, вы фильтруете по ним доску.</p>

    <ul class="onboarding-chips" aria-label="Категории доски">
      <li v-for="c in board.categories" :key="c.id" class="onboarding-chip">
        <span class="onboarding-chip-color" :class="{ colorless: !c.color }" :style="swatch(c.color)" aria-hidden="true" />
        {{ c.name }}
        <button type="button" class="onboarding-chip-remove" :aria-label="`Убрать категорию ${c.name}`" :disabled="busy" @click="remove(c.id)">×</button>
      </li>
    </ul>

    <template v-if="suggestions.length">
      <p class="onboarding-sub">Ещё варианты — нажмите, чтобы добавить</p>
      <div class="onboarding-suggest">
        <button v-for="s in suggestions" :key="s.name" type="button" :disabled="busy" :aria-label="`Добавить категорию ${s.name}`" @click="add(s.name, s.color)">
          <span class="onboarding-chip-color" :style="swatch(s.color)" aria-hidden="true" />{{ s.name }}<span class="onboarding-suggest-plus" aria-hidden="true">+</span>
        </button>
      </div>
    </template>

    <form class="onboarding-add" @submit.prevent="addOwn">
      <InputComponent v-model="own" placeholder="Своя категория" aria-label="Своя категория" maxlength="40" />
      <button type="submit" class="btn" :disabled="!own.trim() || busy">Добавить</button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { errorMessage } from '@/api/client'
import type { PaletteColor } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { entityColorHex } from '@/shared/utils/entityColor'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Onboarding step 3: the board's categories. «Баг» and «Идея» come with every new board; the suggestions are kinds
// of feedback any product gets, not guesses about the product itself. Every change is saved at once
// (the same store calls as CategoriesEditor), so «Дальше» just moves on.
const board = useBoardStore()
const notification = useNotificationStore()
const busy = ref(false)
const own = ref('')

const SUGGESTED: { name: string; color: PaletteColor }[] = [
  { name: 'Баг', color: 'red' },
  { name: 'Идея', color: 'blue' },
  { name: 'Улучшение', color: 'green' },
  { name: 'Вопрос', color: 'yellow' },
  { name: 'Жалоба', color: 'orange' },
  { name: 'Похвала', color: 'pink' },
]
const suggestions = computed(() => {
  const taken = new Set(board.categories.map((c) => c.name.trim().toLowerCase()))
  return SUGGESTED.filter((s) => !taken.has(s.name.toLowerCase()))
})

function swatch(color: PaletteColor | null) {
  const hex = entityColorHex(color, 'category')
  return hex ? { backgroundColor: hex } : {}
}

async function run(action: () => Promise<void>) {
  busy.value = true
  try {
    await action()
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busy.value = false
  }
}
const add = (name: string, color: PaletteColor | null) => run(() => board.addCategory(name, color))
const remove = (id: number) => run(() => board.deleteCategory(id))
async function addOwn() {
  const name = own.value.trim()
  if (!name) return
  await add(name, null)
  own.value = ''
}

// saved as you go
const submit = async (): Promise<boolean> => true
defineExpose({ submit })
</script>
