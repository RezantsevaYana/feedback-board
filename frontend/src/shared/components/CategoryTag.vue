<template>
  <span v-if="category" class="type slant" :data-color="category.color ?? 'none'" :style="entityColorStyle(category.color)">{{ category.name }}</span>
</template>

<script setup lang="ts">
import { entityColorStyle } from '@/shared/utils/entityColor'
import type { Category } from '@/api/types'

// The tag colour is the one the team picked in Categories; legacy categories
// without a colour fall back to the neutral --tag token.
defineProps<{
  category: Category | null
}>()
</script>
