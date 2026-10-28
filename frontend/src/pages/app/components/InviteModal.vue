<template>
  <BaseModal label="Пригласить людей" @close="emit('close')">
    <form class="invite-modal" novalidate @submit.prevent="send">
      <div class="drawer-top">
        <h2>Пригласить людей</h2>
        <button type="button" class="icon-btn" aria-label="Отмена" @click="close"><AppIcon name="close" /></button>
      </div>

      <section class="invite-step">
        <TextAreaComponent
          v-model="raw"
          label="Почты через запятую или по строкам"
          hint="до 100 за раз"
          rows="6"
          :error="rawError"
          placeholder="anna@acme.com, boris@acme.com&#10;client@othercorp.com"
          autofocus
          @input="rawError = ''"
        />
        <SelectComponent
          v-model="role"
          label="Роль для всей пачки"
          :options="roleOptions"
        />
        <div class="confirm-actions">
          <button type="button" class="btn" :disabled="sending" @click="close">Отмена</button>
          <button type="submit" class="btn btn-primary" :disabled="!parsed.length || sending" :aria-busy="sending">
            {{ sending ? 'Отправляем…' : `Отправить ${parsed.length} ${plural(parsed.length, 'письмо', 'письма', 'писем')}` }}
          </button>
        </div>
      </section>
    </form>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { InvitationsApi, type Invitation } from '@/api/participants'
import { errorMessage } from '@/api/client'
import { ASSIGNABLE_ROLES, ROLE_HINTS, ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'
import { plural } from '@/shared/utils/format'

// One step: emails in a textarea, one role for the whole batch, one POST that creates everything.
// The server dedupes already-members into "skipped"; we surface them in the toast.
type AssignableRole = Exclude<ProjectRole, 'owner'>

const props = defineProps<{
  slug: string
}>()
const emit = defineEmits<{
  close: []
  sent: [created: Invitation[]]
}>()

const notification = useNotificationStore()
const closeModal = inject<() => void>('baseModal:close', () => emit('close'))

const roleOptions = ASSIGNABLE_ROLES.map((value) => ({ value, label: ROLE_LABELS[value], hint: ROLE_HINTS[value] }))
const role = ref<AssignableRole>('voter')

const raw = ref('')
const rawError = ref('')
const parsed = computed(() => parseEmails(raw.value))
const sending = ref(false)

function parseEmails(input: string): string[] {
  const seen = new Set<string>()
  const result: string[] = []
  for (const part of input.split(/[,\s;]+/)) {
    const email = part.trim().toLowerCase()
    if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email) || seen.has(email)) continue
    seen.add(email)
    result.push(email)
  }
  return result
}

async function send() {
  if (sending.value) return
  if (!parsed.value.length) {
    rawError.value = 'Добавьте хотя бы одну валидную почту.'
    return
  }
  sending.value = true
  try {
    const result = await InvitationsApi.create(props.slug, {
      role: role.value,
      invitees: parsed.value.map((email) => ({ email })),
    })
    const sentCount = result.data.length
    const skipped = result.skipped.length
    if (sentCount) {
      notification.success(
        `Отправлено ${sentCount} ${plural(sentCount, 'письмо', 'письма', 'писем')}`
          + (skipped ? ` · пропущено ${skipped} (уже на доске)` : ''),
      )
    } else if (skipped) {
      notification.success(`Все ${skipped} уже на доске — ничего не отправили`)
    }
    emit('sent', result.data)
    close()
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    sending.value = false
  }
}

function close() {
  closeModal()
}
</script>