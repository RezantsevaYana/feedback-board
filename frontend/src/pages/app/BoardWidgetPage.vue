<template>
  <BoardWorkspace :slug="slug" page="widget">
    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="settings && embed">
      <!-- a closed board shows nothing on the sites; it comes back by itself once the board is open (TEST.md п. 22) -->
      <section v-if="settings.visibility === 'private'" id="widget" class="board-settings-section">
        <h2>Виджет на сайте</h2>
        <div class="board-access-off">
          <p><strong>Виджет скрыт.</strong> Пока доска закрыта, кнопка «Обратная связь» на сайте не показывается. Код можно не убирать: когда снова откроете доску, виджет появится сам.</p>
        </div>
      </section>

      <template v-else>
        <!-- on a phone the code and the domains fold; the colour and the label below stay open -->
        <FoldSection id="widget" title="Установка" class="board-settings-section" summary="Код для сайта продукта">
          <p class="note">Кнопка «Обратная связь» на сайте продукта. Вставьте тег перед закрывающим <code class="mono">&lt;/body&gt;</code>.</p>
          <pre class="board-access-code"><code>{{ embed.script_tag }}</code></pre>
          <CopyButton :value="embed.script_tag" label="Скопировать код" class="board-access-copy" />
        </FoldSection>

        <FoldSection id="domains" title="Домены" class="board-settings-section" :summary="domainsSummary">
          <SettingsDomains :settings="settings" @saved="onDomainsSaved" />
        </FoldSection>

        <WidgetColorSection :slug="slug" :color="embed.widget_color" @saved="embed.widget_color = $event" />
        <WidgetLauncherSection :slug="slug" :text="embed.widget_launcher_text" @saved="embed.widget_launcher_text = $event" />
      </template>
    </template>

    <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>
  </BoardWorkspace>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { type BoardSettings, BoardsApi, type EmbedInfo } from '@/api/boards'
import CopyButton from '@/shared/ui/CopyButton.vue'
import FoldSection from '@/shared/ui/FoldSection.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import SettingsDomains from './components/SettingsDomains.vue'
import WidgetColorSection from './components/WidgetColorSection.vue'
import WidgetLauncherSection from './components/WidgetLauncherSection.vue'
import { boardLoadError } from './composables/boardLoadError'
import { NO_CONFIGURE_TEXT, useLostAccess } from './composables/useLostAccess'

// «Виджет» — everything about the button on the product's site (TEST.md п. 42): the code, the domains it may run on,
// its colour and label. Moved here from «Доступ» as is; the router sends the old /access#widget and /settings#domains.
const route = useRoute()

const slug = computed(() => String(route.params.slug ?? ''))
const settings = ref<BoardSettings | null>(null)
const embed = ref<EmbedInfo | null>(null)
const loading = ref(true)
const loadError = ref('')
// folded «Домены»: the sites themselves, or why the widget won't work without one
const domainsSummary = computed(() => {
  const list = settings.value?.allowed_origins ?? []
  return list.length ? list.join(', ') : 'Не указаны — виджет не сможет отправлять предложения'
})

watch(slug, load, { immediate: true })
useLostAccess(slug, () => {
  settings.value = null
  loadError.value = NO_CONFIGURE_TEXT
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [s, e] = await Promise.all([BoardsApi.show(slug.value), BoardsApi.embed(slug.value)])
    settings.value = s.data
    embed.value = e.data
  } catch (e) {
    settings.value = null
    loadError.value = boardLoadError(e)
  } finally {
    loading.value = false
  }
  if (settings.value && route.hash) {
    await nextTick()
    document.querySelector(route.hash)?.scrollIntoView()
  }
}

function onDomainsSaved(next: BoardSettings) {
  settings.value = next
  if (embed.value) embed.value.allowed_origins = next.allowed_origins
}
</script>
