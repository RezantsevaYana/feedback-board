<template>
  <!-- inside the frame: just the components, so the phone / tablet widths show their real media queries -->
  <main v-if="bare" class="container base-page ui-kit ui-kit-bare">
    <UiKitSections />
  </main>
  <main v-else class="container base-page ui-kit">
    <header class="ui-kit-head">
      <div>
        <h1>UI-компоненты</h1>
        <p class="note">Только в локальной разработке. Всё, из чего собран интерфейс, и где это используется. Ширина «Планшет» и «Телефон» показывает страницу в рамке этой ширины — с её адаптивом.</p>
      </div>
      <div class="ui-kit-controls">
        <RadioCardsComponent v-model="width" name="ui-kit-width" :options="WIDTHS" class="ui-kit-widths" />
        <ThemeToggle />
      </div>
    </header>
    <UiKitSections v-if="width === 'full'" />
    <iframe v-else :src="frameSrc" class="ui-kit-frame" :class="width" title="UI-компоненты в узкой ширине" />
  </main>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import RadioCardsComponent from '@/shared/ui/RadioCardsComponent.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'
import UiKitSections from './components/UiKitSections.vue'

// /dev/ui — the components catalogue for local development only (the route exists when import.meta.env.DEV).
// One place to see every shared piece in every state, light and dark, desktop / tablet / phone, and bring
// new screens to the same look (TEST.md п. 34).
type Width = 'full' | 'tablet' | 'phone'
const WIDTHS: { value: Width; label: string }[] = [
  { value: 'full', label: 'Десктоп' },
  { value: 'tablet', label: 'Планшет · 768' },
  { value: 'phone', label: 'Телефон · 375' },
]
const route = useRoute()
const bare = computed(() => route.query.bare === '1')
const width = ref<Width>('full')
const frameSrc = computed(() => `${route.path}?bare=1`)
</script>
