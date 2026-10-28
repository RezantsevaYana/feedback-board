<template>
  <ul class="pending-invites">
    <li v-for="invite in invitations" :key="invite.id" class="pending-invite">
      <div class="pending-invite-main">
        <div class="pending-invite-email">{{ invite.email }}</div>
        <div class="pending-invite-meta">
          {{ roleLabel(invite.role) }}
          · истекает {{ fmtDate(invite.expires_at) }}
          <template v-if="invite.invited_by.name">· позвал {{ invite.invited_by.name }}</template>
        </div>
      </div>
      <div class="pending-invite-actions">
        <button type="button" class="btn btn-small" :disabled="busyId === invite.id" @click="resend(invite)">
          Отправить ещё раз
        </button>
        <button type="button" class="btn btn-small btn-ghost" :disabled="busyId === invite.id" @click="cancel(invite)">
          Отменить
        </button>
      </div>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { InvitationsApi, type Invitation } from '@/api/participants'
import { errorMessage } from '@/api/client'
import { ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { fmtDate } from '@/shared/utils/format'

// Pending invitations for a board — each row is a letter waiting on the recipient to click.
// "Отправить ещё раз" rotates the token on the server and mails a fresh letter; "Отменить" deletes.
const props = defineProps<{
  slug: string
  invitations: Invitation[]
}>()
const emit = defineEmits<{
  resend: [id: number]
  cancel: [id: number]
}>()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const busyId = ref<number | null>(null)

function roleLabel(role: ProjectRole) {
  return ROLE_LABELS[role]
}

async function resend(invite: Invitation) {
  busyId.value = invite.id
  try {
    await InvitationsApi.resend(props.slug, invite.id)
    notification.success(`Письмо отправили ещё раз на ${invite.email}`)
    emit('resend', invite.id)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyId.value = null
  }
}

async function cancel(invite: Invitation) {
  const ok = await confirm.ask({
    title: 'Отменить приглашение?',
    message: `Старая ссылка перестанет работать. ${invite.email} сможет попасть на доску только по новому приглашению.`,
    confirmText: 'Отменить приглашение',
    danger: true,
  })
  if (!ok) return
  busyId.value = invite.id
  try {
    await InvitationsApi.destroy(props.slug, invite.id)
    emit('cancel', invite.id)
    notification.success('Приглашение отменено')
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyId.value = null
  }
}
</script>
