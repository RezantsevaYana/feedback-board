<template>
  <div class="app-page">
    <div class="app-page-head">
      <div class="app-page-title">
        <h1>{{ title ?? PAGE_TITLES[page] }}</h1>
        <div class="app-page-actions">
          <!-- «Доступ» has the link as a block of its own, with its own copy button -->
          <CopyButton v-if="page !== 'access'" :value="boardUrl" label="Скопировать ссылку" />
          <!-- on a phone a 40px square with just the arrow; the label stays for screen readers -->
          <a :href="boardUrl" target="_blank" rel="noopener" class="btn btn-primary app-page-open" aria-label="Перейти на доску">
            <span class="app-page-open-full" aria-hidden="true">Перейти на доску →</span><span class="app-page-open-short" aria-hidden="true">→</span>
          </a>
        </div>
      </div>
      <p v-if="$slots.lead" class="page-lead"><slot name="lead" /></p>
    </div>
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CopyButton from '@/shared/ui/CopyButton.vue'

// Head of a board's two pages in the cabinet («Настройки», «Доступ»): the page's name, the board's link to share
// and «Перейти на доску» (opens in a new tab so the cabinet stays available). The switch between the pages is the side menu.
const props = defineProps<{
  slug: string
  page: 'settings' | 'access'
  // replaces the page's name in the heading (e.g. «Доска «…» готова» right after creating it)
  title?: string
}>()

const PAGE_TITLES = {
  settings: 'Настройки',
  access: 'Доступ',
}
// the board lives in this same app, at /<slug>
const boardUrl = computed(() => `${window.location.origin}/${props.slug}`)
</script>