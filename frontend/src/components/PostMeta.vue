<script setup lang="ts">
import type { Post } from '@/api/types'
import { TEXT } from '@/lib/dict'
import { fmtDate } from '@/lib/format'
import AppIcon from './AppIcon.vue'

defineProps<{ post: Post; replyLabel?: boolean }>()
</script>

<template>
  <div class="meta">
    <span>{{ post.author_name || TEXT.anonymous }}</span>
    <span>{{ fmtDate(post.created_at) }}</span>
    <span v-if="post.attachments_count" title="Скриншоты"><AppIcon name="clip" :size="14" /> {{ post.attachments_count }}</span>
    <span v-if="post.team_comment" title="Есть ответ команды"><AppIcon name="reply" :size="14" /><template v-if="replyLabel"> ответ команды</template></span>
  </div>
</template>