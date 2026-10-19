<script setup lang="ts">
import type { Post } from '@/api/types'
import { PRIORITY, STATUS } from '@/lib/dict'
import CategoryTag from './CategoryTag.vue'

// Category tag, optional status pill and (in team mode) priority / hidden marks.
defineProps<{ post: Post; status?: boolean; team?: boolean }>()
</script>

<template>
  <span class="row" style="gap: 6px">
    <CategoryTag :category="post.category" />
    <span v-if="status" class="pill" :data-s="post.status">{{ STATUS[post.status] }}</span>
    <template v-if="team">
      <span v-if="post.priority" class="prio-tag" :data-p="post.priority">{{ PRIORITY[post.priority] }}</span>
      <span v-if="post.is_hidden" class="chip">скрыто</span>
    </template>
  </span>
</template>