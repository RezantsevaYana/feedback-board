<template>
  <BaseModal :label="heading" @close="emit('close')">
    <form class="invite-modal" novalidate @submit.prevent="send">
      <div class="drawer-top">
        <h2>{{ heading }}</h2>
        <button type="button" class="icon-btn" aria-label="Отмена" @click="close"><AppIcon name="close" /></button>
      </div>

      <section class="invite-step">
        <TextAreaComponent
          v-model="raw"
          label="Почты через запятую или по строкам"
          hint="до 100 за раз"
          rows="6"
          :error="fieldError"
          placeholder="anna@acme.com, boris@acme.com&#10;client@othercorp.com"
          autofocus
          @input="rawError = ''"
        />
        <SelectComponent
          v-model="role"
          label="Роль для всей пачки"
          :hint="teamOnly ? 'на открытой доске — только команда' : undefined"
          :options="roleOptions"
        />
        <div class="confirm-actions">
          <button type="button" class="btn" :disabled="sending" @click="close">Отмена</button>
          <button type="submit" class="btn btn-primary" :disabled="!parsed.valid.length || sending" :aria-busy="sending">
            {{ sending ? 'Отправляем…' : `Отправить ${parsed.valid.length} ${plural(parsed.valid.length, 'письмо', 'письма', 'писем')}` }}
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
import { ASSIGNABLE_ROLES, ROLE_HINTS, ROLE_LABELS, TEAM_ROLES, type ProjectRole } from '@/api/types'
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
  /** an open board: only Админ / Менеджер, everyone else is a guest by the link */
  teamOnly?: boolean
}>()
const emit = defineEmits<{
  close: []
  sent: [created: Invitation[]]
}>()

const notification = useNotificationStore()
const closeModal = inject<() => void>('baseModal:close', () => emit('close'))

const heading = computed(() => (props.teamOnly ? 'Добавить в команду' : 'Пригласить людей'))
const roleOptions = (props.teamOnly ? TEAM_ROLES : ASSIGNABLE_ROLES).map((value) => ({ value, label: ROLE_LABELS[value], hint: ROLE_HINTS[value] }))
const role = ref<AssignableRole>(props.teamOnly ? 'manager' : 'voter')

const raw = ref('')
const rawError = ref('')
const parsed = computed(() => parseEmails(raw.value))
const sending = ref(false)
// what doesn't look like an email is the field's error: red and right under it, so it isn't missed.
// It doesn't block the rest — the button counts only the valid ones
const fieldError = computed(() => {
  if (rawError.value) return rawError.value
  const invalid = parsed.value.invalid
  if (!invalid.length) return ''
  const list = invalid.join(', ')
  return invalid.length === 1
    ? `«${list}» — не похоже на почту, сюда письмо не уйдёт. Исправьте или удалите.`
    : `Не похожи на почту: ${list}. Сюда письма не уйдут — исправьте или удалите.`
})

/**
 * Split the textarea into valid emails + the tokens that don't look like one.
 * Both lists come back so the UI can tell the user which pieces were ignored
 * (бага «молча пропадает» в docs/BUGS.md).
 */
function parseEmails(input: string): { valid: string[]; invalid: string[] } {
  const seen = new Set<string>()
  const valid: string[] = []
  const invalid: string[] = []
  const emailRe = /^[^@\s]+@[^@\s]+\.[^@\s]+$/
  for (const part of input.split(/[,\s;]+/)) {
    const token = part.trim()
    if (!token) continue
    const email = token.toLowerCase()
    if (seen.has(email)) continue
    seen.add(email)
    if (emailRe.test(email)) valid.push(email)
    else invalid.push(token)
  }
  return { valid, invalid }
}

async function send() {
  if (sending.value) return
  if (!parsed.value.valid.length) {
    rawError.value = 'Добавьте хотя бы одну валидную почту.'
    return
  }
  sending.value = true
  try {
    const result = await InvitationsApi.create(props.slug, {
      role: role.value,
      invitees: parsed.value.valid.map((email) => ({ email })),
    })
    const sentCount = result.data.length
    const skipped = result.skipped.length
    if (sentCount) {
      notification.success(
        `Отправили ${sentCount} ${plural(sentCount, 'приглашение', 'приглашения', 'приглашений')}`
          + (skipped ? `, ${skipped} ${plural(skipped, 'человек', 'человека', 'человек')} уже на доске` : ''),
      )
    } else if (skipped) {
      notification.error(skipped === 1
        ? 'Этот человек уже на доске — приглашение не нужно'
        : `Все ${skipped} ${plural(skipped, 'человек', 'человека', 'человек')} уже на доске — приглашения не нужны`)
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