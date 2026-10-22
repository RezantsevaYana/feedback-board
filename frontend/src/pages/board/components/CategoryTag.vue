<template>
  <span v-if="category" class="type slant" :data-color="category.color ?? 'none'">{{ category.name }}</span>
</template>

<script setup lang="ts">
import type { Category } from '@/api/types'

// The tag colour is the one the team picked in Categories; legacy categories
// without a colour fall back to the neutral --tag token.
defineProps<{
  category: Category | null
}>()
</script>
