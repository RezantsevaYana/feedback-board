<template>
  <form class="onboarding-step" novalidate @submit.prevent="submit">
    <h1>Оставьте первое предложение</h1>
    <p class="onboarding-lead">Пустая доска выглядит заброшенной. Первая карточка покажет пользователям, как это работает, а вы сами пройдёте их путь.</p>

    <div v-if="board.categories.length" class="field">
      <span>Категория</span>
      <div class="onboarding-pick" role="radiogroup" aria-label="Категория">
        <button
          v-for="c in board.categories"
          :key="c.id"
          type="button"
          role="radio"
          :aria-checked="category === c.id"
          @click="category = c.id"
        >
          <span class="onboarding-chip-color" :style="swatch(c.color)" aria-hidden="true" />{{ c.name }}
        </button>
      </div>
    </div>
    <InputComponent v-model="title" label="Заголовок" placeholder="Например: «Тёмная тема в приложении»" maxlength="120" :error="errors.title" @input="errors.title = ''" />
    <TextAreaComponent v-model="description" label="Подробнее" hint="что хотите и зачем" rows="4" maxlength="5000" :error="errors.description" @input="errors.description = ''" />
    <p v-if="serverError" class="err">{{ serverError }}</p>
    <button type="submit" hidden />
  </form>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { PostsApi } from '@/api/posts'
import type { PaletteColor } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { entityColorHex } from '@/shared/utils/entityColor'
import { voterToken } from '@/shared/utils/voter'
import InputComponent from '@/shared/ui/InputComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// Onboarding step 6: the owner leaves the first post themselves, through the same public API as the board's form,
// and walks the users' path. The preview shows the card as it's typed (the «draft» event).
const props = defineProps<{
  slug: string
}>()
const emit = defineEmits<{
  draft: [draft: { title: string; category: { name: string; color: PaletteColor | null } | null }]
  published: [title: string]
}>()
const board = useBoardStore()

const category = ref<number | null>(board.categories[0]?.id ?? null)
const title = ref('')
const description = ref('')
const errors = reactive({ title: '', description: '' })
const serverError = ref('')

const picked = computed(() => board.categories.find((c) => c.id === category.value) ?? null)
watch([title, picked], () => emit('draft', {
  title: title.value.trim(),
  category: picked.value ? { name: picked.value.name, color: picked.value.color } : null,
}), { immediate: true })

function swatch(color: PaletteColor | null) {
  const hex = entityColorHex(color, 'category')
  return hex ? { backgroundColor: hex } : {}
}

// the same limits as the board's form (StorePostRequest)
function validate() {
  errors.title = title.value.trim().length < 3 ? 'Заголовок — хотя бы 3 символа.' : ''
  errors.description = description.value.trim().length < 10 ? 'Опишите подробнее, хотя бы 10 символов.' : ''
  return !errors.title && !errors.description
}

async function submit(): Promise<boolean> {
  serverError.value = ''
  if (!validate()) return false
  const body = new FormData()
  if (category.value !== null) body.append('category_id', String(category.value))
  body.append('title', title.value.trim())
  body.append('description', description.value.trim())
  body.append('voter_token', voterToken())
  try {
    await PostsApi.create(props.slug, body)
    // the store has held this board since step 3 and BoardPage won't reload an open board:
    // re-read its posts, so «Открыть доску» shows the new card without a page reload
    await board.refresh(true)
    emit('published', title.value.trim())
    return true
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      errors.title = e.field('title') ?? ''
      errors.description = e.field('description') ?? ''
      if (!errors.title && !errors.description) serverError.value = e.message
    } else {
      serverError.value = errorMessage(e)
    }
    return false
  }
}

defineExpose({ submit })
</script>
