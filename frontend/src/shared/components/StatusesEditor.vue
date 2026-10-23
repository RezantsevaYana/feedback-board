<template>
  <p class="note">
    Столбцы доски и фильтр «Статус». «Новое», «Готово» и «Отклонено» — базовые: их можно переименовать,
    но нельзя удалить. Свои столбцы встают между «Новое» и «Готово».
  </p>

  <ul class="cat-list">
    <li v-for="s in board.statuses" :key="s.id">
      <form v-if="editingId === s.id" class="cat-edit" @submit.prevent="saveEdit">
        <div class="cat-row">
          <InputComponent v-model="editName" maxlength="40" aria-label="Название столбца" autofocus />
          <button class="btn btn-primary" type="submit">Сохранить</button>
          <button class="btn" type="button" @click="editingId = null">Отмена</button>
        </div>
        <ColorPaletteComponent v-model="editColor" label="Цвет столбца" variant="status" />
        <div class="cat-preview">
          <span class="label">Как будет выглядеть:</span>
          <span class="pill" :data-color="editColor ?? 'none'">{{ editName.trim() || s.name }}</span>
        </div>
        <span v-if="editError" class="err cat-err">{{ editError }}</span>
      </form>
      <div v-else class="cat-row">
        <span class="pill" :data-color="s.color ?? 'none'">{{ s.name }}</span>
        <span v-if="s.is_system" class="chip">базовый</span>
        <span class="spacer" />
        <button class="link-btn" type="button" @click="startEdit(s)">Переименовать</button>
        <button v-if="!s.is_system" class="link-btn" type="button" @click="askRemove(s)">Удалить</button>
      </div>
    </li>
  </ul>

  <form class="cat-edit" @submit.prevent="add">
    <div class="cat-row">
      <InputComponent
        v-model="newName"
        maxlength="40"
        placeholder="Новый столбец, например «В работе»"
        aria-label="Новый столбец"
        @input="addError = ''"
      />
      <button class="btn btn-primary" type="submit" :disabled="!newName.trim()">Добавить</button>
    </div>
    <ColorPaletteComponent v-model="newColor" label="Цвет нового столбца" variant="status" />
    <div v-if="newName.trim()" class="cat-preview">
      <span class="label">Как будет выглядеть:</span>
      <span class="pill" :data-color="newColor ?? 'none'">{{ newName.trim() }}</span>
    </div>
    <span v-if="addError" class="err cat-err">{{ addError }}</span>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '@/api/client'
import type { PaletteColor, Status } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import ColorPaletteComponent from '@/shared/ui/ColorPaletteComponent.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Team settings: columns of the kanban. Три системных (new/done/rejected) защищены от
// удаления; переименование и смена цвета разрешены всем. Удаление кастомного — посты
// переезжают в «Новое». Работает с проектом, открытым в useBoardStore.
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const newName = ref('')
const newColor = ref<PaletteColor | null>(null)
const addError = ref('')
const editingId = ref<number | null>(null)
const editName = ref('')
const editColor = ref<PaletteColor | null>(null)
const editError = ref('')

const message = (e: unknown) => (e instanceof ApiError ? (e.field('name') ?? e.field('color') ?? e.message) : String(e))

async function add() {
  addError.value = ''
  if (!newName.value.trim()) return
  try {
    await board.addStatus(newName.value.trim(), newColor.value)
    newName.value = ''
    newColor.value = null
  } catch (e) {
    addError.value = message(e)
  }
}

function startEdit(s: Status) {
  editingId.value = s.id
  editName.value = s.name
  editColor.value = s.color
  editError.value = ''
}

async function saveEdit() {
  if (editingId.value === null) return
  try {
    await board.updateStatus(editingId.value, editName.value.trim(), editColor.value)
    editingId.value = null
  } catch (e) {
    editError.value = message(e)
  }
}

async function askRemove(s: Status) {
  const ok = await confirm.ask({
    title: 'Удалить столбец?',
    message: `Удалить «${s.name}»? Карточки из этого столбца переедут в «Новое».`,
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await board.deleteStatus(s.id)
    notification.success('Столбец удалён, карточки переехали в «Новое»')
  } catch (e) {
    notification.error(message(e))
  }
}
</script>