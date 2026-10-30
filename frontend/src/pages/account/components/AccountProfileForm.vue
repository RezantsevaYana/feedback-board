<template>
  <form class="account-form" novalidate @submit.prevent="save">
    <InputComponent
      v-model="name"
      label="Имя"
      hint="подписывает ваши предложения на досках"
      type="text"
      autocomplete="name"
      maxlength="80"
      :error="error"
      @input="error = ''"
    />
    <InputComponent :model-value="email" label="Почта" hint="не меняется" type="text" readonly />
    <div class="settings-actions">
      <button class="btn btn-primary" type="submit" :disabled="!changed || saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'

// «Профиль»: the name is editable, the email is shown read-only (changing it isn't in V1).
const auth = useAuthStore()
const notification = useNotificationStore()

const saved = computed(() => auth.me?.name ?? '')
const email = computed(() => auth.me?.email ?? '')
const name = ref(saved.value)
const error = ref('')
const saving = ref(false)
const changed = computed(() => name.value.trim() !== saved.value)

watch(saved, (next) => (name.value = next))

async function save() {
  if (!name.value.trim()) {
    error.value = 'Представьтесь — хотя бы имя.'
    return
  }
  saving.value = true
  try {
    await auth.updateName(name.value.trim())
    notification.success('Имя сохранено')
  } catch (e) {
    const field = e instanceof ApiError ? e.field('name') : null
    if (field) error.value = field
    else notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>