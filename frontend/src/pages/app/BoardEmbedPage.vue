<template>
  <AppHeader />
  <main class="container base-page board-workspace-page">
    <BoardWorkspace :slug="slug" :name="boardName" page="embed" :title="justCreated ? `Доска «${boardName}» готова` : undefined">
      <template v-if="justCreated" #lead>
        Осталось подключить её к продукту: виджетом на сайте, ссылкой или обоими способами сразу.
      </template>

      <p v-if="loading" class="note">Загружаем…</p>

      <template v-else-if="info">
        <!-- until the email is confirmed the API answers visitors 403 (EnsureProjectPublished); the banner above has the resend button -->
        <p v-if="auth.me && !auth.me.email_verified" class="board-embed-warn">
          Пока почта не подтверждена, виджет и ссылка не работают для посетителей: они увидят «Доска ещё не открыта».
          Подтвердите почту по ссылке из письма, и доска откроется сама.
        </p>
        <section class="board-settings-section board-embed-section">
          <div class="board-embed-section-head">
            <h2>Виджет на сайте</h2>
            <p class="note">Вставьте тег перед закрывающим <code class="mono">&lt;/body&gt;</code>.</p>
          </div>
          <pre class="board-embed-code"><code>{{ info.script_tag }}</code></pre>
          <CopyButton :value="info.script_tag" label="Скопировать код" />
          <p v-if="info.allowed_origins.length" class="note board-embed-origins">
            Работает на: <code v-for="o in info.allowed_origins" :key="o" class="mono">{{ o }}</code>
            <RouterLink :to="domainsLink">изменить</RouterLink>
          </p>
          <p v-else class="board-embed-warn">
            Доменов пока нет, поэтому виджет не сможет отправлять посты.
            <RouterLink :to="domainsLink">Добавить домен</RouterLink>
          </p>
        </section>

        <section class="board-settings-section board-embed-section">
          <div class="board-embed-section-head">
            <h2>Прямая ссылка</h2>
            <p class="note">Автор откроет доску и оставит идею прямо там.</p>
          </div>
          <pre class="board-embed-code"><code>{{ info.direct_url }}</code></pre>
          <div class="board-embed-link-row">
            <CopyButton :value="info.direct_url" label="Скопировать ссылку" />
            <a :href="info.direct_url" class="btn btn-primary" target="_blank" rel="noopener">Открыть доску ↗</a>
          </div>
        </section>

        <div v-if="justCreated" class="board-embed-actions">
          <RouterLink to="/app/boards" class="btn btn-primary">Готово</RouterLink>
        </div>
      </template>

      <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>
    </BoardWorkspace>
  </main>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { BoardsApi, type EmbedInfo } from '@/api/boards'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import CopyButton from '@/shared/ui/CopyButton.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import { boardLoadError } from './composables/boardLoadError'

const route = useRoute()
const auth = useAuthStore()

const info = ref<EmbedInfo | null>(null)
const loading = ref(true)
const loadError = ref('')

const slug = computed(() => String(route.params.slug ?? ''))
const justCreated = computed(() => route.query.created === '1')
const domainsLink = computed(() => `/app/boards/${slug.value}/settings#domains`)
const boardName = computed(() => auth.boardSummaries.find((b) => b.slug === slug.value)?.name ?? slug.value)

// reloads when the header switcher moves to another board's embed page (same route, new slug)
watch(slug, load, { immediate: true })

async function load() {
  loading.value = true
  loadError.value = ''
  info.value = null
  try {
    const res = await BoardsApi.embed(slug.value)
    info.value = res.data
  } catch (e) {
    loadError.value = boardLoadError(e)
  } finally {
    loading.value = false
  }
}
</script>
