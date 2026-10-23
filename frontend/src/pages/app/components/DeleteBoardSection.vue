<template>
  <div class="delete-board">
    <p class="note">
      Доска уйдёт в корзину вместе с предложениями, категориями и столбцами, виджет и ссылка перестанут работать.
      {{ retention }} её можно восстановить из списка досок, потом она удалится навсегда.
    </p>
    <div class="settings-actions">
      <button type="button" class="btn btn-danger" @click="open = true">Удалить доску…</button>
    </div>

    <BaseModal v-if="open" label="Удалить доску" narrow @close="onClosed">
      <form class="delete-board-form" novalidate @submit.prevent="remove">
        <div class="drawer-top">
          <h2>Удалить «{{ settings.name }}»?</h2>
          <button type="button" class="icon-btn" aria-label="Отмена" @click="onClosed"><AppIcon name="close" /></button>
        </div>
        <p class="desc">
          Чтобы подтвердить, введите адрес доски: <code class="mono">{{ settings.slug }}</code>
        </p>
        <InputComponent
          v-model="typed"
          type="text"
          autocapitalize="off"
          autocomplete="off"
          spellcheck="false"
          aria-label="Адрес доски"
          :error="error"
          autofocus
          @input="error = ''"
        />
        <div class="row confirm-actions">
          <button type="button" class="btn" @click="onClosed">Отмена</button>
          <button class="btn btn-danger" type="submit" :disabled="!matches || deleting" :aria-busy="deleting">
            {{ deleting ? 'Удаляем…' : 'Удалить доску' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import type { BoardSettings } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import { fmtDate, plural } from '@/shared/utils/format'

// Danger zone of the settings page. The button stays off until the slug is typed exactly —
// the server checks the same thing. After deleting we go to the hub, where the trash shows it.
const props = defineProps<{
  settings: BoardSettings
}>()
const auth = useAuthStore()
const router = useRouter()
const notification = useNotificationStore()

const retention = computed(() => {
  const n = props.settings.trash_retention_days
  return `${plural(n, 'Первый', 'Первые', 'Первые')} ${n} ${plural(n, 'день', 'дня', 'дней')}`
})

const open = ref(false)
const typed = ref('')
const error = ref('')
const deleting = ref(false)
const matches = computed(() => typed.value.trim() === props.settings.slug)

// Esc and a click on the backdrop come through BaseModal's @close; the buttons close it directly
function onClosed() {
  open.value = false
  typed.value = ''
  error.value = ''
}

async function remove() {
  if (!matches.value || deleting.value) return
  deleting.value = true
  try {
    const slug = props.settings.slug
    const trashed = await auth.trashBoard(slug, typed.value.trim())
    open.value = false
    notification.success(`«${trashed.name}» в корзине до ${fmtDate(trashed.purge_at)}`, {
      label: 'Восстановить',
      run: () => auth.restoreBoard(slug).then(() => notification.success('Доска восстановлена')).catch((e) => notification.error(errorMessage(e))),
    })
    router.push('/app/boards')
  } catch (e) {
    const field = e instanceof ApiError ? e.field('confirm') : null
    if (field) error.value = field
    else notification.error(errorMessage(e))
  } finally {
    deleting.value = false
  }
}
</script>