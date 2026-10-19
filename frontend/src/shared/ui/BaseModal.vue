<template>
  <Teleport to="body">
    <div class="modal-bg" @click.self="emit('close')">
      <component :is="props.tag ?? 'div'" class="modal" :class="{ narrow }" role="dialog" aria-modal="true" :aria-label="label" v-bind="$attrs">
        <slot />
      </component>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import { useEscapeKey } from '@/shared/composables/useEscapeKey'

// Closes on the ✕ (slot), a click on the backdrop and Esc; locks page scroll while open.
// Extra listeners/attrs (e.g. @submit.prevent when tag="form") go to the dialog element, not the Teleport.
defineOptions({ inheritAttrs: false })
const props = defineProps<{
  label: string
  narrow?: boolean
  tag?: string
}>()
const emit = defineEmits<{
  close: []
}>()

// an open screenshot lightbox takes Esc first
useEscapeKey(() => !document.querySelector('.lightbox') && emit('close'))
onMounted(() => document.body.classList.add('locked'))
onBeforeUnmount(() => document.body.classList.remove('locked'))
</script>
