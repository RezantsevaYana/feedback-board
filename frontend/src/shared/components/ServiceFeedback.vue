<template>
  <button type="button" class="service-feedback-link" aria-haspopup="dialog" @click="show">
    <AppIcon name="chat" :size="16" />
    Обратная связь о сервисе
  </button>
  <BaseModal v-if="open" label="Обратная связь о Feedback Board" narrow @close="open = false">
    <div class="drawer-top">
      <h2>Как улучшить Feedback Board?</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="open = false"><AppIcon name="close" /></button>
    </div>
    <div class="service-feedback-intro">
      <p class="note">Нашли баг или есть идея? Расскажите команде сервиса.</p>
      <a :href="boardUrl" target="_blank" rel="noopener">Все предложения и их статусы ↗</a>
    </div>
    <div v-if="sent" class="service-feedback-success" role="status">
      <AppIcon name="check" :size="24" />
      <h3>Спасибо за обратную связь!</h3>
      <p class="note">Предложение уже на доске. По ссылке можно следить за статусом и ответом команды.</p>
      <a :href="`${boardUrl}/posts/${sent.id}`" target="_blank" rel="noopener" class="btn">Открыть предложение ↗</a>
      <button type="button" class="btn btn-primary" @click="sent = null">Отправить ещё</button>
    </div>
    <div v-else-if="error" class="service-feedback-error">
      <p class="alert" role="alert">{{ error }}</p>
      <button type="button" class="btn" @click="load">Попробовать ещё раз</button>
    </div>
    <p v-else-if="!project" class="note" role="status">Загружаем форму…</p>
    <p v-else-if="!project.guests_can_post && !auth.me" class="note">
      Чтобы оставить предложение, <RouterLink to="/app/login" @click="open = false">войдите в аккаунт</RouterLink>.
    </p>
    <SubmitForm v-else :project="project.slug" :categories="project.categories" :author="auth.me" @sent="sent = $event" />
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ProjectsApi } from '@/api/projects'
import { errorMessage } from '@/api/client'
import type { Post, Project } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import SubmitForm from '@/shared/components/SubmitForm.vue'

const auth = useAuthStore()
const open = ref(false)
const project = ref<Project | null>(null)
const sent = ref<Post | null>(null)
const error = ref('')
const boardUrl = computed(() => `/${project.value?.slug ?? 'feedback-board'}`)

async function load() {
  error.value = ''
  project.value = null
  try {
    project.value = (await ProjectsApi.get('feedback-board')).data
  } catch (e) {
    error.value = errorMessage(e)
  }
}

function show() {
  open.value = true
  sent.value = null
  void load()
}
</script>
