<template>
  <BaseModal v-if="confirm.open" :label="confirm.options?.title ?? 'Подтверждение'" narrow @close="onCancel">
    <div class="drawer-top">
      <h2>{{ confirm.options?.title ?? 'Подтверждение' }}</h2>
      <button type="button" class="icon-btn" aria-label="Отмена" @click="onCancel"><AppIcon name="close" /></button>
    </div>
    <p class="desc">{{ confirm.options?.message }}</p>
    <div class="row confirm-actions">
      <!-- focus starts on the safe choice for destructive actions, so a stray Enter doesn't delete -->
      <button type="button" class="btn" :autofocus="confirm.options?.danger || undefined" @click="onCancel">
        {{ confirm.options?.cancelText ?? 'Отмена' }}
      </button>
      <button
        type="button"
        :class="['btn', confirm.options?.danger ? 'btn-danger' : 'btn-primary']"
        :autofocus="!confirm.options?.danger || undefined"
        @click="onConfirm"
      >
        {{ confirm.options?.confirmText ?? 'Продолжить' }}
      </button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'

// Монтируется один раз в App.vue. Триггерится через useConfirmStore().ask(...).
const confirm = useConfirmStore()

function onCancel() {
  confirm.resolve(false)
}
function onConfirm() {
  confirm.resolve(true)
}
</script>
