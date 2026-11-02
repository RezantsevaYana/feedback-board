<template>
  <section class="incoming-invites" aria-labelledby="incoming-invites-title">
    <h2 id="incoming-invites-title">Вас приглашают · {{ invitations.length }}</h2>
    <ul class="incoming-invites-list">
      <li v-for="invite in invitations" :key="invite.id" class="incoming-invite">
        <div class="incoming-invite-main">
          <div class="incoming-invite-board">{{ invite.board.name }}</div>
          <div class="incoming-invite-meta">
            {{ ROLE_LABELS[invite.role] }}
            <template v-if="invite.invited_by.name">· пригласил {{ invite.invited_by.name }}</template>
            · {{ fmtTimeAgo(invite.created_at) }}
          </div>
        </div>
        <div class="incoming-invite-actions">
          <button type="button" class="btn btn-sm btn-primary" :disabled="busyId === invite.id" @click="accept(invite)">
            Принять
          </button>
          <button type="button" class="btn btn-sm btn-danger" :disabled="busyId === invite.id" @click="decline(invite)">
            Отклонить
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { IncomingInvitation } from '@/api/participants'
import { errorMessage } from '@/api/client'
import { ROLE_LABELS } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { fmtTimeAgo } from '@/shared/utils/format'

// «Вас приглашают» on top of the hub: invitations to my email, joined or declined without the letter.
// Accepting keeps the person in the cabinet: the board shows up in the list and opens from there in a new tab.
defineProps<{
  invitations: IncomingInvitation[]
}>()
const auth = useAuthStore()
const confirm = useConfirmStore()
const notification = useNotificationStore()

const busyId = ref<number | null>(null)

async function accept(invite: IncomingInvitation) {
  busyId.value = invite.id
  try {
    const res = await auth.acceptIncoming(invite.id)
    notification.success(`Вы в доске «${res.board.name}» — она появилась в списке`)
  } catch (e) {
    notification.error(errorMessage(e))
    // a stale invitation (expired, cancelled meanwhile) shouldn't stay on screen
    await auth.fetchIncoming().catch(() => undefined)
  } finally {
    busyId.value = null
  }
}

async function decline(invite: IncomingInvitation) {
  const ok = await confirm.ask({
    title: `Отклонить приглашение в «${invite.board.name}»?`,
    message: 'Приглашение пропадёт. Чтобы попасть на доску потом, попросите прислать новое.',
    confirmText: 'Отклонить',
    danger: true,
  })
  if (!ok) return
  busyId.value = invite.id
  try {
    await auth.declineIncoming(invite.id)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyId.value = null
  }
}
</script>