<template>
  <BaseModal label="Удалить статус?" narrow :persistent="saving" class="status-delete-modal" @close="emit('close')">
    <div class="drawer-top">
      <h2>Удалить статус?</h2>
      <button class="icon-btn" type="button" aria-label="Отмена" :disabled="saving" @click="emit('close')"><AppIcon name="close" /></button>
    </div>
    <p v-if="loading" class="note" role="status">Проверяем предложения…</p>
    <template v-else-if="info">
      <p class="note">Предложений в статусе «{{ status.name }}»: {{ info.posts_count }}.</p>
      <template v-if="info.statuses.length">
        <SelectComponent portal v-model="replacement" :options="info.statuses.map(s => ({ value: s.id, label: s.name }))" label="Перенести предложения в" :disabled="saving" />
        <p v-if="info.default_status_id === status.id" class="note">Это статус для новых предложений. После удаления они будут попадать в выбранный статус.</p>
        <p v-else class="note">Предложения сохранятся. Отменить удаление статуса нельзя.</p>
      </template>
      <p v-else class="note">Нельзя удалить последний статус. Сначала добавьте другой.</p>
    </template>
    <p v-if="error" class="err" role="alert">{{ error }}</p>
    <button v-if="!loading && !info" class="btn" type="button" @click="load">Повторить</button>
    <div class="row confirm-actions">
      <button class="btn" type="button" :disabled="saving" autofocus @click="emit('close')">Отмена</button>
      <button class="btn btn-danger" type="button" :disabled="loading || saving || !replacement || !info?.statuses.length" :aria-busy="saving" @click="remove">{{ saving ? 'Удаляем…' : 'Удалить статус' }}</button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { StatusesApi, type StatusDeletionInfo } from '@/api/statuses'
import { ApiError, errorMessage } from '@/api/client'
import type { Status } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import BaseModal from '@/shared/ui/BaseModal.vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'

const props = defineProps<{ status: Status }>()
const emit = defineEmits<{ close: [] }>()
const board = useBoardStore()
const notification = useNotificationStore()
const info = ref<StatusDeletionInfo | null>(null)
const replacement = ref<number | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    info.value = (await StatusesApi.deletionInfo(props.status.id)).data
    replacement.value = info.value.statuses.find(s => s.id === info.value?.default_status_id)?.id
      ?? info.value.statuses[0]?.id ?? null
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function remove() {
  if (!replacement.value || saving.value) return
  saving.value = true
  error.value = ''
  try {
    const result = await board.deleteStatus(props.status.id, replacement.value)
    notification.success(result.moved_posts_count ? 'Статус удалён, предложения перенесены' : 'Статус удалён')
    emit('close')
  } catch (e) {
    error.value = e instanceof ApiError
      ? e.field('replacement_status_id') ?? e.field('status') ?? e.message
      : errorMessage(e)
  } finally {
    saving.value = false
  }
}
onMounted(load)
</script>

<style scoped lang="scss">
.note, .err { margin: 0; }
:deep(.field) { margin: 0; }
@media screen and (max-width: 1023px) {
  :deep(.input) { min-height: 36px; padding-block: 5px; font-size: 16px; }
  .btn { min-height: 36px; padding-inline: 10px; }
}
</style>
