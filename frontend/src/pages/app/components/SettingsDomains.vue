<template>
  <div class="widget-domains">
    <p class="note">Укажите сайты, на которых установлен ваш виджет. Сервер разрешит виджету получать настройки и отправлять предложения только с этих адресов.</p>
    <ul v-if="settings.allowed_origins.length" class="widget-domains-list">
      <li v-for="origin in settings.allowed_origins" :key="origin">
        <span class="widget-domains-origin">{{ origin }}</span>
        <button class="link-btn" type="button" :disabled="saving" :aria-label="`Убрать домен ${origin}`" @click="remove(origin)">Убрать</button>
      </li>
    </ul>
    <p v-else class="note">Домены не указаны — виджет не сможет отправлять предложения.</p>
    <form class="widget-domains-add" novalidate @submit.prevent="add">
      <InputComponent v-model="input" type="text" placeholder="https://site.ru" aria-label="Домен сайта" autocomplete="url" autocapitalize="off" spellcheck="false" :disabled="saving" :error="error" @input="error = ''" />
      <button class="btn" type="submit" :disabled="saving || !input.trim()" :aria-busy="saving" aria-label="Добавить домен"><span class="widget-domains-full">Добавить</span><span class="widget-domains-short" aria-hidden="true">+</span></button>
    </form>
    <p class="note widget-domains-hint">Укажите адрес сайта с https:// или http://, без названия страницы. Например: https://site.ru, а не https://site.ru/contacts. Адреса с www и без www добавляйте отдельно. Эти настройки не ограничивают вход на саму доску. Для локальной разработки — <code class="mono">http://localhost:*</code>.</p>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'

const props = defineProps<{ settings: BoardSettings }>()
const emit = defineEmits<{ saved: [settings: BoardSettings] }>()
const notification = useNotificationStore()
const input = ref('')
const error = ref('')
const saving = ref(false)
watch(() => props.settings.slug, () => { input.value = ''; error.value = '' })

async function add() {
  if (saving.value) return
  const origin = input.value.trim().replace(/\/+$/, '')
  if (!origin) return
  if (props.settings.allowed_origins.includes(origin)) {
    error.value = 'Этот домен уже добавлен'
    return
  }
  const list = [...props.settings.allowed_origins, origin]
  if (await persist(list, list.length - 1)) input.value = ''
}

async function remove(origin: string) {
  if (saving.value) return
  await persist(props.settings.allowed_origins.filter(value => value !== origin))
}

async function persist(origins: string[], addedIndex?: number) {
  saving.value = true
  error.value = ''
  try {
    const res = await BoardsApi.update(props.settings.slug, { origins })
    emit('saved', res.data)
    notification.success(addedIndex === undefined ? 'Домен убран' : 'Домен добавлен')
    return true
  } catch (e) {
    const field = e instanceof ApiError && addedIndex !== undefined
      ? e.field(`origins.${addedIndex}`) ?? e.field('origins')
      : null
    if (field) error.value = field
    else notification.error(errorMessage(e))
    return false
  } finally {
    saving.value = false
  }
}
</script>

<style scoped lang="scss">
@use '@/scss/utils' as *;
.widget-domains { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
.note { margin: 0; }
.widget-domains-list {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 6px;
  list-style: none;
  padding: 0;
  margin: 0;
  li {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: 10px;
  }
  .link-btn { flex: none; margin-left: auto; }
}
.widget-domains-origin { min-width: 0; overflow-wrap: anywhere; }
.widget-domains-add {
  display: flex;
  align-items: start;
  gap: 8px;
  > .field { flex: 1 1 0; min-width: 0; margin: 0; }
  > .btn { flex: none; }
}
.widget-domains-short { display: none; }
.widget-domains-hint { font-size: 12px; }
@media screen and (max-width: $mobile) {
  .widget-domains { gap: 8px; }
  .widget-domains-list {
    gap: 0;
    border: 1px solid var(--line);
    border-radius: 12px;
    li { min-height: var(--settings-control-size, 44px); padding: 4px 8px 4px 12px; border: 0; border-bottom: 1px solid var(--line); border-radius: 0; }
    li:first-child { border-radius: 11px 11px 0 0; }
    li:last-child { border-radius: 0 0 11px 11px; border-bottom: 0; }
    .link-btn { min-height: 36px; }
  }
  .widget-domains-add {
    :deep(.input) { min-height: var(--settings-control-size, 44px); font-size: 16px; }
    > .btn { width: var(--settings-control-size, 44px); height: var(--settings-control-size, 44px); padding: 0; }
  }
  .widget-domains-full { display: none; }
  .widget-domains-short { display: inline; font-size: 18px; line-height: 1; }
}
</style>
