<template>
  <p class="note">
    Статусы доски и фильтр «Статус». «Новое», «Готово» и «Отклонено» — базовые: их можно переименовать,
    но нельзя удалить и переместить. Свои статусы встают между «Новое» и «Готово» — порядок меняется
    перетаскиванием за ручку слева или через «⋯» → «Выше / Ниже».
  </p>

  <ul class="cat-list cat-list-drag">
    <!-- reorder by the grip with pointer events: mouse and finger alike (HTML5 drag & drop does nothing on touch) -->
    <li
      v-for="s in board.statuses"
      :key="s.id"
      :data-status-id="s.id"
      :class="{ draggable: !s.is_system, dragging: dragId === s.id, 'drag-over': dragOverId === s.id }"
    >
      <form v-if="editingId === s.id" class="cat-edit" @submit.prevent="saveEdit">
        <!-- says what this is: the field holds the name being edited («Готово» alone read like a button) -->
        <p class="cat-edit-caption">Изменить статус</p>
        <div class="cat-row editing">
          <ColorPaletteComponent v-model="editColor" v-model:valid="editColorValid" label="Цвет статуса" variant="status" />
          <InputComponent v-model="editName" maxlength="40" aria-label="Название статуса" autofocus />
          <button class="btn btn-primary" type="submit" :disabled="!editColorValid">Сохранить</button>
          <!-- phone: just «×»; the word stays for screen readers -->
          <button class="btn cat-cancel" type="button" aria-label="Отмена" @click="editingId = null"><span class="cat-cancel-word">Отмена</span><AppIcon name="close" :size="16" class="cat-cancel-icon" /></button>
        </div>
        <div class="cat-preview">
          <span class="label">Как будет выглядеть:</span>
          <span class="pill" :data-color="editColor ?? 'none'" :style="entityColorStyle(editColor)">{{ editName.trim() || s.name }}</span>
        </div>
        <span v-if="editError" class="err cat-err">{{ editError }}</span>
      </form>
      <div v-else class="cat-row">
        <!-- the grip is only interactive for custom columns; system ones get a dash in its place so names align -->
        <span
          v-if="!s.is_system"
          class="cat-grip"
          aria-label="Перетащите, чтобы изменить порядок"
          title="Перетащите, чтобы изменить порядок"
          @pointerdown="startDrag(s, $event)"
        ><AppIcon name="grip" :size="16" /></span>
        <span v-else class="cat-grip cat-grip-empty" aria-hidden="true" />
        <span class="pill" :data-color="s.color ?? 'none'" :style="entityColorStyle(s.color)">{{ s.name }}</span>
        <span v-if="s.is_system" class="chip">базовый</span>
        <span class="spacer" />
        <button class="link-btn" type="button" @click="startEdit(s)">Изменить</button>
        <button v-if="!s.is_system" class="link-btn" type="button" @click="askRemove(s)">Удалить</button>
        <!-- phone: one «⋯»; it also moves a custom column up / down, the keyboard's way to reorder -->
        <!-- the wrapper carries the class: MenuComponent hands its attributes to its trigger button -->
        <span class="cat-more"><MenuComponent :items="rowMenu(s)" :aria-label="`Действия: ${s.name}`" /></span>
      </div>
    </li>
  </ul>

  <form class="cat-edit" @submit.prevent="add">
    <div class="cat-row">
      <ColorPaletteComponent v-model="newColor" v-model:valid="newColorValid" label="Цвет нового статуса" variant="status" />
      <InputComponent
        v-model="newName"
        maxlength="40"
        placeholder="Новый статус, например «В работе»"
        aria-label="Новый статус"
        @input="addError = ''"
      />
      <button class="btn btn-add" type="submit" :disabled="!newName.trim() || !newColorValid">Добавить</button>
    </div>
    <div v-if="newName.trim()" class="cat-preview">
      <span class="label">Как будет выглядеть:</span>
      <span class="pill" :data-color="newColor ?? 'none'" :style="entityColorStyle(newColor)">{{ newName.trim() }}</span>
    </div>
    <span v-if="addError" class="err cat-err">{{ addError }}</span>
  </form>
</template>

<script setup lang="ts">
import { entityColorStyle } from '@/shared/utils/entityColor'
import { onBeforeUnmount, ref } from 'vue'
import { ApiError } from '@/api/client'
import type { PaletteColor, Status } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import ColorPaletteComponent from '@/shared/ui/ColorPaletteComponent.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'

// Team settings: columns of the kanban. Три системных (new/done/rejected) защищены от
// удаления; переименование и смена цвета разрешены всем. Удаление кастомного — посты
// переезжают в «Новое». Работает с проектом, открытым в useBoardStore.
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

// the order of custom columns (system ones stay put): dragged by the grip, or «⋯» → Выше / Ниже
const dragId = ref<number | null>(null)
const dragOverId = ref<number | null>(null)

const customStatuses = () => board.statuses.filter((s) => !s.is_system)

// pointer events, not HTML5 drag & drop: one path for mouse and finger. The grip has touch-action: none,
// so dragging it doesn't scroll the page; the row under the pointer gets the «drop here» line
function startDrag(s: Status, e: PointerEvent) {
  if (editingId.value !== null || e.button > 0) return
  e.preventDefault()
  dragId.value = s.id
  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('pointercancel', stopDrag)
}
function onPointerMove(e: PointerEvent) {
  const row = document.elementFromPoint(e.clientX, e.clientY)?.closest<HTMLElement>('[data-status-id]')
  const id = row ? Number(row.dataset.statusId) : null
  const target = board.statuses.find((s) => s.id === id)
  dragOverId.value = target && !target.is_system && target.id !== dragId.value ? target.id : null
}
function onPointerUp() {
  const from = dragId.value
  const to = dragOverId.value
  stopDrag()
  if (from !== null && to !== null) void moveTo(from, to)
}
function stopDrag() {
  dragId.value = null
  dragOverId.value = null
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', onPointerUp)
  window.removeEventListener('pointercancel', stopDrag)
}
onBeforeUnmount(stopDrag)

// puts the column `sourceId` where `targetId` is, among the custom ones
async function moveTo(sourceId: number, targetId: number) {
  const custom = customStatuses()
  const from = custom.findIndex((s) => s.id === sourceId)
  const to = custom.findIndex((s) => s.id === targetId)
  if (from < 0 || to < 0 || from === to) return
  const next = custom.slice()
  // from is a valid index above, so splice always returns one element
  const moved = next.splice(from, 1)[0]!
  next.splice(to, 0, moved)
  try {
    await board.reorderStatuses(next.map((s) => s.id))
  } catch (e) {
    notification.error(message(e))
  }
}

function rowMenu(s: Status): MenuItem[] {
  if (s.is_system) return [{ label: 'Изменить', run: () => startEdit(s) }]
  const custom = customStatuses()
  const index = custom.findIndex((c) => c.id === s.id)
  return [
    { label: 'Изменить', run: () => startEdit(s) },
    { label: 'Выше', run: () => moveTo(s.id, custom[index - 1]!.id), disabled: index <= 0, separated: true },
    { label: 'Ниже', run: () => moveTo(s.id, custom[index + 1]!.id), disabled: index >= custom.length - 1 },
    { label: 'Удалить', run: () => askRemove(s), danger: true, separated: true },
  ]
}

const message = (e: unknown) => (e instanceof ApiError ? (e.field('name') ?? e.field('color') ?? e.message) : String(e))

async function add() {
  addError.value = ''
  if (!newName.value.trim() || !newColorValid.value) return
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
  editColorValid.value = true
}

async function saveEdit() {
  if (editingId.value === null || !editColorValid.value) return
  try {
    await board.updateStatus(editingId.value, editName.value.trim(), editColor.value)
    editingId.value = null
  } catch (e) {
    editError.value = message(e)
  }
}

async function askRemove(s: Status) {
  const ok = await confirm.ask({
    title: 'Удалить статус?',
    message: `Удалить «${s.name}»? Карточки с этим статусом перейдут в «Новое».`,
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await board.deleteStatus(s.id)
    notification.success('Статус удалён, карточки переехали в «Новое»')
  } catch (e) {
    notification.error(message(e))
  }
}
</script>