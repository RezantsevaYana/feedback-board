<template>
  <BaseModal label="Столбцы" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Столбцы</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <p class="note">
      Столбцы доски и фильтр «Статус». «Новое», «Готово» и «Отклонено» — базовые: их можно переименовать,
      но нельзя удалить. Свои столбцы встают между «Новое» и «Готово».
    </p>

    <ul class="cat-list">
      <li v-for="s in board.statuses" :key="s.id">
        <form v-if="editingId === s.id" class="cat-row" @submit.prevent="saveEdit">
          <InputComponent v-model="editName" maxlength="40" aria-label="Название столбца" autofocus />
          <button class="btn btn-primary" type="submit">Сохранить</button>
          <button class="btn" type="button" @click="editingId = null">Отмена</button>
          <span v-if="editError" class="err cat-err">{{ editError }}</span>
        </form>
        <div v-else class="cat-row">
          <span class="pill" :data-s="s.slug">{{ s.name }}</span>
          <span v-if="s.is_system" class="chip">базовый</span>
          <span class="spacer" />
          <button class="link-btn" type="button" @click="startEdit(s.id, s.name)">Переименовать</button>
          <button v-if="!s.is_system" class="link-btn" type="button" @click="askRemove(s)">Удалить</button>
        </div>
      </li>
    </ul>

    <form class="cat-row" @submit.prevent="add">
      <InputComponent
        v-model="newName"
        maxlength="40"
        placeholder="Новый столбец, например «В работе»"
        aria-label="Новый столбец"
        @input="addError = ''"
      />
      <button class="btn btn-primary" type="submit" :disabled="!newName.trim()">Добавить</button>
      <span v-if="addError" class="err cat-err">{{ addError }}</span>
    </form>
  </BaseModal>
</template>

<script setup lang="ts">
import { inject, ref } from 'vue'
import { ApiError } from '@/api/client'
import type { Status } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Team settings: columns of the kanban. Три системных (new/done/rejected) защищены от
// удаления; переименование разрешено всем. Удаление кастомного — посты переезжают в «Новое».
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

const newName = ref('')
const addError = ref('')
const editingId = ref<number | null>(null)
const editName = ref('')
const editError = ref('')

const message = (e: unknown) => (e instanceof ApiError ? (e.field('name') ?? e.message) : String(e))

async function add() {
  addError.value = ''
  if (!newName.value.trim()) return
  try {
    await board.addStatus(newName.value.trim())
    newName.value = ''
  } catch (e) {
    addError.value = message(e)
  }
}

function startEdit(id: number, name: string) {
  editingId.value = id
  editName.value = name
  editError.value = ''
}

async function saveEdit() {
  if (editingId.value === null) return
  try {
    await board.renameStatus(editingId.value, editName.value.trim())
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
