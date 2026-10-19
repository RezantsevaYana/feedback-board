<template>
  <div class="meta">
    <span>{{ post.author_name || TEXT.anonymous }}</span>
    <span>{{ fmtDate(post.created_at) }}</span>
    <span v-if="post.attachments_count" title="Скриншоты"><AppIcon name="clip" :size="14" /> {{ post.attachments_count }}</span>
    <span v-if="post.team_comment" title="Есть ответ команды"><AppIcon name="reply" :size="14" /><template v-if="replyLabel"> ответ команды</template></span>
  </div>
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import AppIcon from '@/shared/ui/AppIcon.vue'

defineProps<{
  post: Post
  replyLabel?: boolean
}>()
</script>
