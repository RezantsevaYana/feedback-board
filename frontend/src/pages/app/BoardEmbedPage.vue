<template>
  <AppHeader />
  <main class="container base-page board-embed">
    <RouterLink to="/app/boards" class="board-embed-back">← Все доски</RouterLink>

    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="info">
      <section v-if="justCreated" class="board-embed-welcome">
        <h1>Доска «{{ boardName }}» готова</h1>
        <p>Теперь подключите её к продукту — одним из двух способов.</p>
      </section>
      <section v-else class="board-embed-welcome">
        <h1>Как подключить доску «{{ boardName }}»</h1>
        <p>Два способа, оба можно использовать вместе.</p>
      </section>

      <section class="board-embed-block">
        <header class="board-embed-block-head">
          <h2>① Виджет на вашем сайте</h2>
          <p class="note">Вставьте этот тег перед закрывающим <code class="mono">&lt;/body&gt;</code>.</p>
        </header>
        <pre class="board-embed-code"><code>{{ info.script_tag }}</code></pre>
        <CopyButton :value="info.script_tag" label="Скопировать код" />
        <div class="board-embed-origins">
          <template v-if="info.allowed_origins.length">
            <p class="note">
              Работает на: <code v-for="o in info.allowed_origins" :key="o" class="mono">{{ o }}</code>
              <RouterLink :to="domainsLink">изменить</RouterLink>
            </p>
          </template>
          <template v-else>
            <p class="board-embed-warn">
              Домены ещё не добавлены — виджет не сможет отправлять посты.
              <RouterLink :to="domainsLink">Добавить домен</RouterLink>
            </p>
          </template>
        </div>
      </section>

      <section class="board-embed-block">
        <header class="board-embed-block-head">
          <h2>② Прямая ссылка</h2>
          <p class="note">Поделитесь ссылкой — автор оставит идею прямо на доске.</p>
        </header>
        <pre class="board-embed-code"><code>{{ info.direct_url }}</code></pre>
        <div class="board-embed-link-row">
          <CopyButton :value="info.direct_url" label="Скопировать ссылку" />
          <a :href="info.direct_url" class="btn" target="_blank" rel="noopener">Открыть доску ↗</a>
        </div>
      </section>

      <div v-if="justCreated" class="board-embed-finish">
        <RouterLink to="/app/boards" class="btn">Готово, вернуться в hub</RouterLink>
      </div>
    </template>

    <p v-else class="note">Доску не удалось загрузить. Вернитесь в <RouterLink to="/app/boards">hub</RouterLink>.</p>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { BoardsApi, type EmbedInfo } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import CopyButton from '@/shared/ui/CopyButton.vue'

const route = useRoute()
const auth = useAuthStore()
const notification = useNotificationStore()

const info = ref<EmbedInfo | null>(null)
const loading = ref(true)

const slug = computed(() => String(route.params.slug ?? ''))
const justCreated = computed(() => route.query.created === '1')
const domainsLink = computed(() => `/app/boards/${slug.value}/settings#domains`)
const boardName = computed(() => auth.boardSummaries.find((b) => b.slug === slug.value)?.name ?? slug.value)

onMounted(async () => {
  try {
    const res = await BoardsApi.embed(slug.value)
    info.value = res.data
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    loading.value = false
  }
})
</script>
