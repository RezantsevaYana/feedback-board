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

      <section v-else class="board-settings-section widget-settings">
        <WidgetPreview embedded :color="previewColor" :text="previewText" />

        <WidgetAppearanceSection
          :slug="slug" :color="embed.widget_color" :text="embed.widget_launcher_text"
          @preview="previewColor = $event.color; previewText = $event.text"
          @saved="embed.widget_color = $event.color; embed.widget_launcher_text = $event.text"
        />

        <!-- The preview and appearance stay visible; installation and domains fold on mobile. -->
        <FoldSection id="widget" title="Установка" class="widget-subsection" summary="Код для сайта продукта">
          <p class="note">Вставьте тег перед закрывающим <code class="mono">&lt;/body&gt;</code>.</p>
          <pre class="board-access-code"><code>{{ embed.script_tag }}</code></pre>
          <div class="board-access-actions">
            <CopyButton :value="embed.script_tag" label="Скопировать код" class="board-access-copy" />
          </div>
        </FoldSection>

        <FoldSection id="domains" title="Домены" class="widget-subsection" :summary="domainsSummary">
          <SettingsDomains :settings="settings" @saved="onDomainsSaved" />
        </FoldSection>

      </section>
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
import WidgetAppearanceSection from './components/WidgetAppearanceSection.vue'
import WidgetPreview from './components/WidgetPreview.vue'
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
const previewColor = ref<string | null>(null)
const previewText = ref('')
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

<style lang="scss">
@use '@/scss/utils' as *;

.widget-settings {
  gap: 14px;
  min-width: 0;
  width: 100%;
  max-width: 760px;

  .widget-overview > h2 { font-size: 18px; }
  .fold-section-title { font-size: 15px; }
  .fold-section-body { gap: 10px; }
  .fold-section-body > .note { margin: 0; }
  .widget-subsection:not(.foldable) {
    display: grid;
    gap: 10px;
  }

  .widget-overview { display: grid; gap: 14px; }
  .widget-subsection {
    padding-top: 14px;
    border-top: 1px solid var(--line);
    min-width: 0;
  }
  .widget-appearance {
    display: grid;
    gap: 10px;
    h3 { margin: 0; font-size: 15px; }
    fieldset {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 10px;
      min-width: 0;
      padding: 0;
      margin: 0;
      border: 0;
    }
    .field { margin: 0; min-width: 0; }
    .note { margin: 0; }
  }
  .widget-appearance-label { font-size: 13px; font-weight: 500; }
  .widget-appearance-colors {
    display: flex;
    flex-wrap: wrap;
    align-items: start;
    gap: 8px;
    > .field { width: 120px; }
    .btn { gap: 8px; }
    .input { font-family: var(--font-mono); }
  }
  .widget-appearance-actions { display: flex; justify-content: end; }

  @media screen and (max-width: $mobile) {
    width: auto;
    .widget-subsection { @include bleed; padding-top: 14px; }
    .widget-subsection.foldable { padding-top: 0; }
    .fold-section-body { gap: 8px; }
    .board-access-actions .btn { min-height: var(--settings-control-size, 44px); }
    .widget-appearance { gap: 8px; }
    .widget-appearance-colors {
      flex-wrap: nowrap;
      > .field { flex: 1; width: auto; }
      > .btn, .widget-color-dropdown { flex: none; }
    }
    .widget-appearance .btn,
    .widget-appearance .input { min-height: var(--settings-control-size, 44px); }
    .widget-appearance .input { font-size: 16px; }
  }
}
</style>
