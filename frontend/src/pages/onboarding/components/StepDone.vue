<template>
  <div class="onboarding-step onboarding-done">
    <span class="onboarding-done-mark" aria-hidden="true"><AppIcon name="check" :size="26" /></span>
    <h1>Доска готова</h1>
    <p class="onboarding-lead">
      <template v-if="published">Ваше предложение «{{ published }}» уже на доске. Поделитесь ссылкой, чтобы пришли остальные.</template>
      <template v-else>Поделитесь ссылкой, чтобы пришли первые предложения.</template>
    </p>
    <div class="onboarding-share">
      <code>{{ boardUrl }}</code>
      <CopyButton :value="boardUrl" label="Скопировать" />
    </div>
    <div class="onboarding-done-actions">
      <RouterLink :to="`/${slug}`" class="btn btn-primary">Открыть доску →</RouterLink>
      <RouterLink :to="{ name: 'board-settings', params: { slug } }" class="btn">Все настройки</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/shared/ui/AppIcon.vue'
import CopyButton from '@/shared/ui/CopyButton.vue'

// The end of the onboarding: the board's link to share, the board itself, or the settings for the rest.
const props = defineProps<{
  slug: string
  // the first post's title, if the owner left one on step 6
  published: string
}>()
const boardUrl = computed(() => `${window.location.origin}/${props.slug}`)
</script>
