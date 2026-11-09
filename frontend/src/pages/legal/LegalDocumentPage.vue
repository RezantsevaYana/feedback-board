<template>
  <AppHeader />
  <main class="container base-page legal-page">
    <p v-if="loading" class="note">Загружаем документ…</p>
    <template v-else-if="document">
      <h1>{{ document.title }}</h1>
      <p class="note">Редакция {{ document.version }}</p>
      <LegalDocumentBody :document="document" />
    </template>
    <div v-else><p class="alert">Не удалось загрузить документ.</p><button class="btn" type="button" @click="load">Попробовать ещё раз</button></div>
  </main>
</template>
<script setup lang="ts">
import { ref, watch } from 'vue'
import { LegalApi, type LegalDocument, type LegalDocumentKey } from '@/api/legal'
import AppHeader from '@/shared/components/AppHeader.vue'
import LegalDocumentBody from '@/shared/components/LegalDocumentBody.vue'
const props = defineProps<{ documentKey: LegalDocumentKey }>()
const loading = ref(false)
const document = ref<LegalDocument | null>(null)
async function load() {
  const key = props.documentKey
  loading.value = true
  document.value = null
  try {
    const response = await LegalApi.catalog()
    if (key === props.documentKey) document.value = response.data[key]
  } catch { /* The page keeps a retry action. */ }
  finally { if (key === props.documentKey) loading.value = false }
}
watch(() => props.documentKey, load, { immediate: true })
</script>
