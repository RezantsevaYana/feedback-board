<template>
  <span class="row post-tags">
    <CategoryTag :category="post.category" />
    <span v-if="status" class="pill" :data-s="post.status.slug">{{ post.status.name }}</span>
    <template v-if="team">
      <span v-if="post.priority" class="prio-tag" :data-p="post.priority">{{ PRIORITY[post.priority] }}</span>
      <span v-if="post.is_hidden" class="chip">скрыто</span>
    </template>
  </span>
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { PRIORITY } from '@/shared/constants/dict'
import CategoryTag from './CategoryTag.vue'

// Category tag, optional status pill and (in team mode) priority / hidden marks.
defineProps<{
  post: Post
  status?: boolean
  team?: boolean
}>()
</script>
