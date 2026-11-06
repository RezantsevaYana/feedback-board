<template>
  <p class="note">О чём бывают предложения в этом проекте. Пользователь выбирает категорию в форме, на доске по ним можно фильтровать.</p>

  <ul class="cat-list">
    <li v-for="c in board.categories" :key="c.id">
      <form v-if="editingId === c.id" class="cat-edit" @submit.prevent="saveEdit">
        <div class="cat-row editing">
          <ColorPaletteComponent v-model="editColor" v-model:valid="editColorValid" label="Цвет категории" variant="category" />
          <InputComponent v-model="editName" maxlength="40" aria-label="Название категории" autofocus />
          <button class="btn btn-primary" type="submit" :disabled="!editColorValid">Сохранить</button>
          <button class="btn" type="button" @click="editingId = null">Отмена</button>
        </div>
        <div class="cat-preview">
          <span class="label">Как будет выглядеть:</span>
          <span class="type slant" :data-color="editColor ?? 'none'" :style="entityColorStyle(editColor)">{{ editName.trim() || c.name }}</span>
        </div>
        <span v-if="editError" class="err cat-err">{{ editError }}</span>
      </form>
      <div v-else class="cat-row">
        <CategoryTag :category="c" />
        <span class="spacer" />
        <button class="link-btn" type="button" @click="startEdit(c)">Изменить</button>
        <button class="link-btn" type="button" @click="askRemove(c)">Удалить</button>
      </div>
    </li>
    <li v-if="!board.categories.length" class="note">Категорий нет — форма отправки будет без выбора.</li>
  </ul>

  <form class="cat-edit" @submit.prevent="add">
    <div class="cat-row">
      <ColorPaletteComponent v-model="newColor" v-model:valid="newColorValid" label="Цвет новой категории" variant="category" />
      <InputComponent v-model="newName" maxlength="40" placeholder="Новая категория, например «Доставка»" aria-label="Новая категория" @input="addError = ''" />
      <button class="btn btn-add" type="submit" :disabled="!newName.trim() || !newColorValid">Добавить</button>
    </div>
    <div v-if="newName.trim()" class="cat-preview">
      <span class="label">Как будет выглядеть:</span>
      <span class="type slant" :data-color="newColor ?? 'none'" :style="entityColorStyle(newColor)">{{ newName.trim() }}</span>
    </div>
    <span v-if="addError" class="err cat-err">{{ addError }}</span>
  </form>
</template>

<script setup lang="ts">
import { entityColorStyle } from '@/shared/utils/entityColor'
import { ref } from 'vue'
import { ApiError } from '@/api/client'
import type { Category, PaletteColor } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import ColorPaletteComponent from '@/shared/ui/ColorPaletteComponent.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import CategoryTag from './CategoryTag.vue'

// Team settings: what posts can be about. Shown in the widget form, on the board filters and on cards.
// Works on the project opened in useBoardStore; rendered in the board modal and on the settings page.
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const newName = ref('')
const newColor = ref<PaletteColor | null>(null)
const addError = ref('')
const newColorValid = ref(true)
const editColorValid = ref(true)
const editingId = ref<number | null>(null)
const editName = ref('')
const editColor = ref<PaletteColor | null>(null)
const editError = ref('')

const message = (e: unknown) => (e instanceof ApiError ? (e.field('name') ?? e.field('color') ?? e.message) : String(e))

async function add() {
  addError.value = ''
  if (!newName.value.trim() || !newColorValid.value) return
  try {
    await board.addCategory(newName.value.trim(), newColor.value)
    newName.value = ''
    newColor.value = null
  } catch (e) {
    addError.value = message(e)
  }
}

function startEdit(c: Category) {
  editingId.value = c.id
  editName.value = c.name
  editColor.value = c.color
  editError.value = ''
  editColorValid.value = true
}

async function saveEdit() {
  if (editingId.value === null || !editColorValid.value) return
  try {
    await board.updateCategory(editingId.value, editName.value.trim(), editColor.value)
    editingId.value = null
  } catch (e) {
    editError.value = message(e)
  }
}

async function askRemove(c: Category) {
  const ok = await confirm.ask({
    title: 'Удалить категорию?',
    message: `Удалить «${c.name}»? Предложения останутся, но без категории.`,
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await board.deleteCategory(c.id)
    notification.success('Категория удалена, её предложения остались без категории')
  } catch (e) {
    notification.error(message(e))
  }
}
</script>
