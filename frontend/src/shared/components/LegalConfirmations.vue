<template>
  <div class="legal-confirmations" :aria-busy="loading">
    <p v-if="loading" class="note">Загружаем документы…</p>
    <p v-if="draft" class="legal-draft-note">Документы пока в черновой редакции для тестирования. Реквизиты оператора и окончательные условия ещё не заполнены.</p>
    <template v-if="model">
      <div v-for="item in fields" :key="item.key" class="legal-confirmation">
        <label class="check legal-check">
          <input type="checkbox" :checked="model[item.key].accepted" :disabled="loading" :aria-invalid="!!errors?.[`legal.${item.key}.accepted`] || undefined" :aria-describedby="errors?.[`legal.${item.key}.accepted`] ? `${uid}-${item.key}` : undefined" @change="change(item.key, ($event.target as HTMLInputElement).checked)">
          <span>{{ item.prefix }} <a :href="item.path" target="_blank" rel="noopener">{{ item.label }}</a></span>
        </label>
        <span v-if="errors?.[`legal.${item.key}.accepted`]" :id="`${uid}-${item.key}`" class="err" role="alert">{{ errors[`legal.${item.key}.accepted`] }}</span>
      </div>
    </template>
    <p v-if="loadError || versionError || errors?.legal" class="err" role="alert">{{ loadError || versionError || errors?.legal }}</p>
    <button v-if="loadError || versionError" type="button" class="link-btn" :disabled="loading" @click="load">Загрузить документы заново</button>
  </div>
</template>
<script setup lang="ts">
import { computed, onMounted, ref, useId } from 'vue'
import { LegalApi, type LegalAcceptanceKey, type LegalPayload } from '@/api/legal'
const model = defineModel<LegalPayload | null>({ required: true })
const props = defineProps<{ errors?: Record<string, string> }>()
const uid = useId()
const loading = ref(false)
const loadError = ref('')
const draft = ref(false)
const fields: Array<{ key: LegalAcceptanceKey; prefix: string; label: string; path: string }> = [
  { key: 'terms', prefix: 'Принимаю', label: 'пользовательское соглашение', path: '/terms' },
  { key: 'personal_data', prefix: 'Даю', label: 'согласие на обработку персональных данных', path: '/personal-data-consent' },
]
const versionError = computed(() => Object.entries(props.errors ?? {}).find(([key]) => key.endsWith('.version') || key.endsWith('.content_hash'))?.[1] ?? '')
function change(key: LegalAcceptanceKey, accepted: boolean) {
  if (!model.value) return
  model.value = { ...model.value, [key]: { ...model.value[key], accepted } }
}
async function load() {
  loading.value = true
  loadError.value = ''
  model.value = null
  try {
    const { data } = await LegalApi.catalog()
    draft.value = data.terms.draft || data.personal_data.draft
    model.value = {
      terms: { accepted: false, version: data.terms.version, content_hash: data.terms.content_hash },
      personal_data: { accepted: false, version: data.personal_data.version, content_hash: data.personal_data.content_hash },
    }
  } catch {
    loadError.value = 'Не удалось загрузить документы. Попробуйте ещё раз перед созданием аккаунта.'
  } finally { loading.value = false }
}
onMounted(load)
</script>
