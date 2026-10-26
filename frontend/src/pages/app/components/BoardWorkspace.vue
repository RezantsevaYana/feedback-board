<template>
  <div class="board-workspace">
    <SettingsNav :slug="slug" :page="page" />
    <div class="board-workspace-main">
      <div class="page-head board-workspace-head">
        <nav class="page-crumbs" aria-label="Путь">
          <RouterLink to="/app/boards">Мои доски</RouterLink>
          <span aria-hidden="true">/</span>
          <span aria-current="page">{{ name }}</span>
        </nav>
        <!-- on a phone the button shrinks to a 40px square arrow, centred against the name; the label stays for screen readers -->
        <div class="board-workspace-title">
          <h1>{{ title ?? name }}</h1>
          <a :href="`/${slug}`" class="btn btn-primary board-workspace-open" target="_blank" rel="noopener" aria-label="Открыть доску в новой вкладке">
            <span class="board-workspace-open-full" aria-hidden="true">Открыть доску ↗</span><span class="board-workspace-open-short" aria-hidden="true">↗</span>
          </a>
        </div>
        <p v-if="$slots.lead" class="page-lead"><slot name="lead" /></p>
      </div>
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import SettingsNav from './SettingsNav.vue'

// Frame of a board's own pages in the cabinet (settings, connection): breadcrumbs, the board's name,
// «Открыть доску» and the same side nav, so moving between them feels like tabs of one place.
defineProps<{
  slug: string
  name: string
  page: 'settings' | 'embed'
  // replaces the board's name in the heading (e.g. «Доска «…» готова» right after creating it)
  title?: string
}>()
</script>