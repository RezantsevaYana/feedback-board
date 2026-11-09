<template>
  <table class="participants-table" :aria-label="teamOnly ? 'Команда доски' : 'Участники доски'">
    <tbody>
      <!-- Members accepted the invitation and signed in. -->
      <tr v-for="m in orderedMembers" :key="`m-${m.user_id}`" :class="{ 'participants-row-owner': m.role === 'owner', 'participants-row-you': m.user_id === meId }">
        <td class="participants-person-cell">
          <span class="participants-person-name">
            {{ m.name }}<span v-if="m.user_id === meId" class="participants-you">Вы</span>
          </span>
          <small class="participants-sub">{{ m.email }}</small>
        </td>
        <td class="participants-role-cell">
          <span v-if="m.role === 'owner'" class="participants-role-text">Владелец</span>
          <div v-else class="participants-member-controls">
            <SelectComponent
              :model-value="m.role"
              :options="roleOptions"
              :aria-label="`Роль ${m.name}`"
              class="participants-role-select"
              portal
              @update:model-value="(next: string) => changeRole(m, next as AssignableRole)"
            />
            <button
              v-if="m.user_id !== meId"
              type="button"
              class="icon-btn participants-action-remove"
              aria-label="Удалить участника"
              @click="askRemoveMember(m)"
            >
              <AppIcon name="close" :size="14" />
            </button>
          </div>
        </td>
      </tr>

      <!-- Pending invitations as regular rows in the same table (test.md п. 23). -->
      <tr v-for="inv in invitations" :key="`i-${inv.id}`" class="participants-row-invite">
        <td class="participants-person-cell">
          <span class="participants-person-name">
            {{ inv.email }}
            <span class="participants-chip pending">Приглашение не принято</span>
          </span>
          <small class="participants-sub">приглашение отправлено {{ fmtDate(inv.sent_at) }}</small>
        </td>
        <td class="participants-role-cell">
          <span class="participants-role-text">{{ ROLE_LABELS[inv.role] }}</span>
          <span class="participants-invite-actions">
            <span class="participants-action-sep" aria-hidden="true">·</span>
            <button type="button" class="link-btn participants-action-link" :disabled="busyInviteId === inv.id" @click="resendInvite(inv)">
              Отправить ещё раз
            </button>
            <span class="participants-action-sep" aria-hidden="true">·</span>
            <button type="button" class="link-btn participants-action-link participants-action-danger" :disabled="busyInviteId === inv.id" @click="cancelInvite(inv)">
              Отменить
            </button>
          </span>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { InvitationsApi, ParticipantsApi, type Invitation, type Participant } from '@/api/participants'
import { ApiError, errorMessage } from '@/api/client'
import { ASSIGNABLE_ROLES, ROLE_HINTS, ROLE_LABELS, TEAM_ROLES, type ProjectRole } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import { fmtDate } from '@/shared/utils/format'

// Members and pending invitations in one table (test.md п. 23). A member row has a role select; an invitation row
// shows the email with a «Приглашение не принято» chip, when the letter went out, and inline «Отправить ещё раз»
// / «Отменить» links next to the role name. Table stays a table on every screen — the prototype keeps it dense
// (frontend/demo/settings-rework-prototype.html), there's no block-by-block stacking.
type AssignableRole = Exclude<ProjectRole, 'owner'>

const props = defineProps<{
  slug: string
  members: Participant[]
  invitations: Invitation[]
  meId: number
  /** an open board: roles only of the team (Админ, Менеджер) */
  teamOnly?: boolean
}>()
const emit = defineEmits<{
  changed: [member: Participant]
  removed: [userId: number]
  inviteCancelled: [id: number]
  inviteResent: [id: number]
}>()
const notification = useNotificationStore()
const confirm = useConfirmStore()

// Keep the current account easy to find, including in a long list of invitations.
const orderedMembers = computed(() => [...props.members].sort((a, b) =>
  Number(b.user_id === props.meId) - Number(a.user_id === props.meId),
))

const roleOptions = computed(() => (props.teamOnly ? TEAM_ROLES : ASSIGNABLE_ROLES).map((value) => ({ value, label: ROLE_LABELS[value], hint: ROLE_HINTS[value] })))

const busyInviteId = ref<number | null>(null)

async function changeRole(member: Participant, nextRole: AssignableRole) {
  if (member.role === nextRole) return
  try {
    const res = await ParticipantsApi.update(props.slug, member.user_id, { role: nextRole })
    emit('changed', res.data)
    notification.success(`${member.name} теперь ${ROLE_LABELS[nextRole].toLowerCase()}`)
  } catch (e) {
    notification.error(fieldOrGeneric(e))
  }
}

async function askRemoveMember(member: Participant) {
  const ok = await confirm.ask({
    title: `Удалить ${member.name} с доски?`,
    message: 'Доступ пропадёт сразу. Голоса на закрытой доске сохранятся.',
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await ParticipantsApi.destroy(props.slug, member.user_id)
    emit('removed', member.user_id)
    notification.success(`${member.name} убран с доски`)
  } catch (e) {
    notification.error(fieldOrGeneric(e))
  }
}

async function resendInvite(invite: Invitation) {
  busyInviteId.value = invite.id
  try {
    await InvitationsApi.resend(props.slug, invite.id)
    emit('inviteResent', invite.id)
    notification.success(`Письмо отправили ещё раз на ${invite.email}`)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyInviteId.value = null
  }
}

async function cancelInvite(invite: Invitation) {
  const ok = await confirm.ask({
    title: 'Отменить приглашение?',
    message: `Старая ссылка перестанет работать. ${invite.email} сможет попасть на доску только по новому приглашению.`,
    confirmText: 'Отменить приглашение',
    danger: true,
  })
  if (!ok) return
  busyInviteId.value = invite.id
  try {
    await InvitationsApi.destroy(props.slug, invite.id)
    emit('inviteCancelled', invite.id)
    notification.success('Приглашение отменено')
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyInviteId.value = null
  }
}

function fieldOrGeneric(e: unknown): string {
  return e instanceof ApiError ? e.message : errorMessage(e)
}
</script>
