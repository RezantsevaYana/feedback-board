<script setup lang="ts">
import { ref } from 'vue'
import type { Attachment } from '@/api/types'

defineProps<{ attachments: Attachment[] }>()
const zoomed = ref<string | null>(null)
</script>

<template>
  <div v-if="attachments.length" class="shots">
    <button v-for="a in attachments" :key="a.id" type="button" :aria-label="`Открыть ${a.original_name}`" @click="zoomed = a.url">
      <img :src="a.url" :alt="a.original_name" loading="lazy" />
    </button>
  </div>
  <Teleport to="body">
    <div v-if="zoomed" class="lightbox" @click="zoomed = null" @keydown.esc="zoomed = null">
      <img :src="zoomed" alt="Скриншот" />
    </div>
  </Teleport>
</template>