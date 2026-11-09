<template>
  <div class="legal-document-body">
    <p v-if="document.draft" class="legal-draft-note">Черновик для подготовки и тестирования. Документ требует заполнения реквизитов оператора и юридической проверки перед публикацией.</p>
    <dl class="legal-operator">
      <div><dt>Оператор</dt><dd>{{ document.operator.name || 'Пока не указан' }}</dd></div>
      <div><dt>Адрес</dt><dd>{{ document.operator.address || 'Пока не указан' }}</dd></div>
      <div><dt>Контакт для обращений</dt><dd><a v-if="contact" :href="`mailto:${contact}`">{{ contact }}</a><span v-else>Пока не указан. До запуска оператору нужно добавить контакт для запросов и отзыва согласия.</span></dd></div>
    </dl>
    <section v-for="section in document.sections" :key="section.title">
      <h2>{{ section.title }}</h2>
      <p>{{ section.text }}</p>
    </section>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { LegalDocumentSnapshot } from '@/api/legal'
const props = defineProps<{ document: LegalDocumentSnapshot }>()
const contact = computed(() => {
  const value = props.document.operator.contact_email
  return value && /^[^\s@?&#]+@[^\s@?&#]+\.[^\s@?&#]+$/.test(value) ? value : null
})
</script>
