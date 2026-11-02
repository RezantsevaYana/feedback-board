<template>
  <div class="account-profile">
    <!-- who you are: initials, name, the address and whether it's confirmed -->
    <div class="account-who">
      <span class="account-avatar" aria-hidden="true">{{ avatar }}</span>
      <div class="account-who-text">
        <span class="account-who-name">{{ saved }}</span>
        <span class="account-who-mail">
          {{ email }}
          <span class="account-mail-status" :class="{ unconfirmed: !verified }">{{ verified ? 'подтверждена' : 'не подтверждена' }}</span>
        </span>
      </div>
    </div>
    <!-- unconfirmed: the cabinet's banner moves here, next to the address it's about (AppHeader skips this page) -->
    <VerifyEmailBanner v-if="!verified" :email="email" class="account-verify" />

    <form class="account-name-row" novalidate @submit.prevent="save">
      <InputComponent
        v-model="name"
        label="Имя"
        hint="так вас подписывают на досках"
        type="text"
        autocomplete="name"
        maxlength="80"
        :error="error"
        @input="error = ''"
      />
      <!-- only once the name changed: at rest there's nothing to save -->
      <button v-if="changed" class="btn btn-primary" type="submit" :disabled="saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>
    </form>
    <p class="note">Почта не меняется: по ней вы входите и получаете письма.</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import VerifyEmailBanner from '@/shared/components/VerifyEmailBanner.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import { initials } from '@/shared/utils/format'

// «Профиль»: who you are on top (initials, name, the address and its status), the name is the one thing to edit;
// the email isn't editable in V1, so it's text, not a greyed-out field.
const auth = useAuthStore()
const notification = useNotificationStore()

const saved = computed(() => auth.me?.name ?? '')
const email = computed(() => auth.me?.email ?? '')
const verified = computed(() => auth.me?.email_verified ?? true)
const avatar = computed(() => initials(saved.value, email.value))
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