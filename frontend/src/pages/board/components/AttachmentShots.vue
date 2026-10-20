<template>
  <div v-if="attachments.length" ref="galleryRef" class="shots">
    <a
      v-for="a in attachments"
      :key="a.id"
      :href="a.url"
      :data-src="a.url"
      :data-sub-html="a.original_name"
      :aria-label="`Открыть ${a.original_name}`"
    >
      <img :src="a.url" :alt="a.original_name" loading="lazy" />
    </a>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import lightGallery, { type LightGallery } from 'lightgallery'
import lgZoom from 'lightgallery/plugins/zoom'
import type { Attachment } from '@/api/types'

const props = defineProps<{
  attachments: Attachment[]
}>()

const galleryRef = ref<HTMLElement | null>(null)
let gallery: LightGallery | null = null

onMounted(() => {
  if (!galleryRef.value) return
  gallery = lightGallery(galleryRef.value, {
    plugins: [lgZoom],
    licenseKey: '0000-0000-000-0000',   // GPL-режим — скрыть предупреждение
    speed: 250,
    download: false,
    mousewheel: true,
    counter: props.attachments.length > 1,
    hideScrollbar: true,
  })
})

watch(
  () => props.attachments.map((a) => a.id).join(','),
  () => gallery?.refresh(),
)

onBeforeUnmount(() => {
  gallery?.destroy()
  gallery = null
})
</script>
