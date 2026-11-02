<template>
  <table class="participants-table" aria-label="Участники доски">
    <thead>
      <tr>
        <th scope="col">Человек</th>
        <th scope="col">Роль</th>
        <th scope="col">Добавлен</th>
        <th scope="col" class="participants-table-actions">
          <span class="visually-hidden">Действия</span>
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="m in members" :key="m.user_id">
        <td>
          <div class="participants-person">
            <div class="participants-person-name">{{ m.name }}<span v-if="m.user_id === meId" class="participants-you">· вы</span></div>
            <div class="participants-person-email">{{ m.email }}</div>
          </div>
        </td>
        <td>
          <!-- Owner's role shows as a plain pill: it's immutable. For everyone else we render a select. -->
          <span v-if="m.role === 'owner'" class="role-chip owner">Владелец</span>
          <SelectComponent
            v-else
            :model-value="m.role"
            :options="roleOptions"
            :aria-label="`Роль ${m.name}`"
            @update:model-value="(next: string) => changeRole(m, next as AssignableRole)"
          />
        </td>
        <td class="participants-source">{{ m.source ? SOURCE_LABELS[m.source] : '' }}</td>
        <td class="participants-table-actions">
          <button
            v-if="m.role !== 'owner' && m.user_id !== meId"
            type="button"
            class="icon-btn"
            aria-label="Удалить участника"
            @click="askRemove(m)"
          >
            <AppIcon name="close" />
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ParticipantsApi, type Participant, type ParticipantSource } from '@/api/participants'
import { ApiError, errorMessage } from '@/api/client'
import { ASSIGNABLE_ROLES, ROLE_HINTS, ROLE_LABELS, TEAM_ROLES, type ProjectRole } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'

// Members table. Each row's role select PATCHes — the row updates
// as soon as the server echoes back. The owner row is cosmetic-only; "yourself" row hides the
// remove action, both guards are mirrored on the server.
type AssignableRole = Exclude<ProjectRole, 'owner'>

const props = defineProps<{
  slug: string
  members: Participant[]
  meId: number
  /** an open board: roles only of the team (Админ, Менеджер) */
  teamOnly?: boolean
}>()
const emit = defineEmits<{
  changed: [member: Participant]
  removed: [userId: number]
}>()
const notification = useNotificationStore()
const confirm = useConfirmStore()

// where the person came from — for an audit of who got in how
const SOURCE_LABELS: Record<ParticipantSource, string> = {
  created: 'создал доску',
  request: 'по запросу',
  invitation: 'по приглашению',
  manual: 'добавлен вручную',
}

const roleOptions = computed(() => (props.teamOnly ? TEAM_ROLES : ASSIGNABLE_ROLES).map((value) => ({ value, label: ROLE_LABELS[value], hint: ROLE_HINTS[value] })))

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


async function askRemove(member: Participant) {
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

function fieldOrGeneric(e: unknown): string {
  return e instanceof ApiError ? e.message : errorMessage(e)
}
</script>
