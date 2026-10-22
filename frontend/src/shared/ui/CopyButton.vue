<template>
  <button type="button" class="btn copy-btn" :class="{ copied }" @click="copy">
    <AppIcon :name="copied ? 'check' : 'copy'" />
    <span>{{ copied ? 'Скопировано' : label }}</span>
  </button>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'

const props = defineProps<{
  value: string
  label?: string
}>()

const copied = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

async function copy() {
  try {
    await navigator.clipboard.writeText(props.value)
  } catch {
    // Clipboard may be blocked (e.g. non-HTTPS, iframe without permissions).
    // Fallback: a hidden textarea + execCommand('copy') kept basic polyfill coverage.
    const ta = document.createElement('textarea')
    ta.value = props.value
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    try {
      document.execCommand('copy')
    } finally {
      document.body.removeChild(ta)
    }
  }
  copied.value = true
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => (copied.value = false), 2000)
}
</script>
