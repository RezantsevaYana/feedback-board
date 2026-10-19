<template>
  <BaseModal label="Категории" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Категории</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
    </div>
    <p class="note">О чём бывают предложения в этом проекте. Пользователь выбирает категорию в форме, на доске по ним можно фильтровать.</p>

    <ul class="cat-list">
      <li v-for="c in board.categories" :key="c.id">
        <form v-if="editingId === c.id" class="cat-row" @submit.prevent="saveEdit">
          <InputComponent v-model="editName" maxlength="40" aria-label="Название категории" autofocus />
          <button class="btn btn-primary" type="submit">Сохранить</button>
          <button class="btn" type="button" @click="editingId = null">Отмена</button>
          <span v-if="editError" class="err cat-err">{{ editError }}</span>
        </form>
        <div v-else-if="deletingId === c.id" class="confirm">
          <span>Удалить «{{ c.name }}»? Предложения останутся, но без категории.</span>
          <span class="spacer" />
          <button class="btn btn-danger" type="button" @click="remove(c.id)">Удалить</button>
          <button class="btn" type="button" @click="deletingId = null">Отмена</button>
        </div>
        <div v-else class="cat-row">
          <CategoryTag :category="c" />
          <span class="spacer" />
          <button class="link-btn" type="button" @click="startEdit(c.id, c.name)">Переименовать</button>
          <button class="link-btn" type="button" @click="(deletingId = c.id), (editingId = null)">Удалить</button>
        </div>
      </li>
      <li v-if="!board.categories.length" class="note">Категорий нет — форма отправки будет без выбора.</li>
    </ul>

    <form class="cat-row" @submit.prevent="add">
      <InputComponent v-model="newName" maxlength="40" placeholder="Новая категория, например «Доставка»" aria-label="Новая категория" @input="addError = ''" />
      <button class="btn btn-primary" type="submit" :disabled="!newName.trim()">Добавить</button>
      <span v-if="addError" class="err cat-err">{{ addError }}</span>
    </form>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import CategoryTag from './CategoryTag.vue'

// Team settings: what posts can be about. Shown in the widget form, on the board filters and on cards.
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()

const newName = ref('')
const addError = ref('')
const editingId = ref<number | null>(null)
const editName = ref('')
const editError = ref('')
const deletingId = ref<number | null>(null)

const message = (e: unknown) => (e instanceof ApiError ? (e.field('name') ?? e.message) : String(e))

async function add() {
  addError.value = ''
  if (!newName.value.trim()) return
  try {
    await board.addCategory(newName.value.trim())
    newName.value = ''
  } catch (e) {
    addError.value = message(e)
  }
}

function startEdit(id: number, name: string) {
  editingId.value = id
  editName.value = name
  editError.value = ''
  deletingId.value = null
}

async function saveEdit() {
  if (editingId.value === null) return
  try {
    await board.renameCategory(editingId.value, editName.value.trim())
    editingId.value = null
  } catch (e) {
    editError.value = message(e)
  }
}

async function remove(id: number) {
  try {
    await board.deleteCategory(id)
    deletingId.value = null
    notification.success('Категория удалена, её предложения остались без категории')
  } catch (e) {
    notification.error(message(e))
  }
}
</script>
