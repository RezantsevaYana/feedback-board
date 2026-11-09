<template>
  <section class="widget-overview" :class="{ 'board-settings-section': !embedded }">
    <h2>Виджет на сайте</h2>
    <p class="note">Виджет — кнопка «Обратная связь» на сайте вашего продукта. По нажатию открывается форма: посетитель отправляет предложение, и оно появляется на этой доске.</p>
    <div class="widget-overview-cases" :style="palette">
      <figure class="widget-overview-case">
        <figcaption>На вашем сайте</figcaption>
        <span class="widget-overview-launcher" :title="label">{{ label }}</span>
      </figure>
      <figure class="widget-overview-case">
        <figcaption>После нажатия</figcaption>
        <div class="widget-overview-panel" role="img" aria-label="Пример формы обратной связи с полями и кнопкой отправки">
          <b>Обратная связь</b>
          <span aria-hidden="true" />
          <span aria-hidden="true" />
          <span class="widget-overview-submit" aria-hidden="true" />
        </div>
      </figure>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { widgetPalette } from '@/shared/utils/widgetColor'

const props = defineProps<{ color: string | null; text: string; embedded?: boolean }>()
const palette = computed(() => widgetPalette(props.color))
const label = computed(() => props.text.trim() || 'Обратная связь')
</script>

<style scoped lang="scss">
@use '@/scss/utils' as *;

.widget-overview > .note { margin: 0; }
.widget-overview-cases {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.widget-overview-case {
  position: relative;
  height: 170px;
  margin: 0;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: repeating-linear-gradient(0deg, var(--surface-2), var(--surface-2) 14px, var(--line) 14px, var(--line) 15px);

  figcaption {
    position: absolute;
    top: 8px;
    left: 10px;
    color: var(--muted);
    font-size: 12px;
  }
}
.widget-overview-launcher {
  position: absolute;
  right: 12px;
  bottom: 12px;
  max-width: calc(100% - 24px);
  padding: 7px 14px 7px 16px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  clip-path: polygon(8px 0, 100% 0, 100% 100%, 0 100%);
  background: var(--accent);
  color: var(--accent-fg);
  font-size: 13px;
  font-weight: 600;
}
.widget-overview-panel {
  position: absolute;
  right: 12px;
  bottom: 12px;
  display: grid;
  align-content: start;
  gap: 6px;
  width: 62%;
  height: 132px;
  padding: 10px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--surface);

  b { font-size: 13px; }
  span {
    height: 16px;
    border-radius: 5px;
    background: var(--surface-2);
  }
  .widget-overview-submit {
    width: 50%;
    justify-self: end;
    background: var(--accent);
  }
}
@media screen and (max-width: $mobile) {
  .widget-overview-cases { gap: 8px; }
  .widget-overview-case {
    height: var(--settings-preview-height, 104px);
    border-radius: 10px;
    figcaption { top: 5px; left: 8px; font-size: 11px; }
  }
  .widget-overview-launcher {
    right: 8px;
    bottom: 8px;
    max-width: calc(100% - 16px);
    padding: 4px 9px 4px 11px;
    font-size: 10px;
  }
  .widget-overview-panel {
    right: 8px;
    bottom: 8px;
    width: 78%;
    height: var(--settings-preview-panel-height, 74px);
    padding: 7px;
    gap: var(--settings-preview-panel-gap, 4px);
    border-radius: 8px;
    b { font-size: 10px; }
    span { height: 9px; }
  }
}
</style>
