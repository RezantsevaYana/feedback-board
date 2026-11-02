<template>
  <form class="settings-domains" novalidate @submit.prevent="save">
    <p class="note">
      Сайты, где стоит виджет: схема и хост, без пути. Для локальной разработки подойдёт <code class="mono">http://localhost:*</code>.
      Без доменов виджет не сможет отправлять предложения.
    </p>
    <div v-for="(row, idx) in rows" :key="row.key" class="settings-domains-row">
      <InputComponent
        v-model="row.value"
        type="text"
        autocapitalize="off"
        spellcheck="false"
        :placeholder="ORIGIN_EXAMPLE"
        aria-label="Домен"
        :error="errors[idx]"
        @input="clearError(idx)"
      />
      <button type="button" class="icon-btn" aria-label="Удалить домен" @click="remove(idx)">
        <AppIcon name="close" />
      </button>
    </div>
    <div class="settings-actions">
      <button type="button" class="btn btn-add" @click="add">+ Добавить домен</button>
      <span class="spacer" />
      <button class="btn btn-primary" type="submit" :disabled="!changed || saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить домены' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Widget domains (allowed_origins). The list is edited locally and saved in one PATCH;
// an empty list is valid — the board then works by direct link only.
const props = defineProps<{
  settings: BoardSettings
}>()
const emit = defineEmits<{
  saved: [settings: BoardSettings]
}>()
const notification = useNotificationStore()
// kept out of the template: a literal "//" there trips vue-tsc and drops the v-for scope
const ORIGIN_EXAMPLE = 'https://app.example.com'

let nextKey = 0
const toRows = (list: string[]) => (list.length ? list : ['']).map((value) => ({ key: nextKey++, value }))

const rows = ref(toRows(props.settings.allowed_origins))
const errors = ref<Record<number, string>>({})
const saving = ref(false)

const clean = computed(() => rows.value.map((o) => o.value.trim().replace(/\/+$/, '')).filter((v) => v.length > 0))
const changed = computed(() => clean.value.join('\n') !== props.settings.allowed_origins.join('\n'))

watch(() => props.settings.allowed_origins, (next) => (rows.value = toRows(next)))

function add() {
  rows.value.push({ key: nextKey++, value: '' })
}

function clearError(idx: number) {
  if (errors.value[idx]) errors.value = { ...errors.value, [idx]: '' }
}

function remove(idx: number) {
  rows.value.splice(idx, 1)
  if (!rows.value.length) add()
  errors.value = {}
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    const res = await BoardsApi.update(props.settings.slug, { origins: clean.value })
    emit('saved', res.data)
    notification.success(res.data.allowed_origins.length ? 'Домены сохранены' : 'Домены убраны — доска работает по прямой ссылке')
  } catch (e) {
    if (e instanceof ApiError && Object.keys(e.errors).length) {
      // the server indexes the cleaned list, so map "origins.N" back onto the non-empty rows
      const filled = rows.value.map((o, idx) => ({ idx, empty: !o.value.trim() })).filter((r) => !r.empty)
      const next: Record<number, string> = {}
      for (const [key, messages] of Object.entries(e.errors)) {
        const match = key.match(/^origins\.(\d+)$/)
        const row = match ? filled[Number(match[1])] : undefined
        if (row && messages[0]) next[row.idx] = messages[0]
      }
      errors.value = next
      if (!Object.keys(next).length) notification.error(e.field('origins') ?? e.message)
    } else {
      notification.error(errorMessage(e))
    }
  } finally {
    saving.value = false
  }
}
</script>