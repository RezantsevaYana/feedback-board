<template>
  <table class="participants-table" aria-label="Участники доски">
    <thead>
      <tr>
        <th scope="col">Человек</th>
        <th scope="col">Роль</th>
        <th scope="col">Статус</th>
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
        <td>
          <!-- Click toggles team / external; the owner's badge is read-only, matches the role pill. -->
          <button
            v-if="m.role !== 'owner'"
            type="button"
            class="team-badge"
            :class="{ team: m.is_team_member, external: !m.is_team_member }"
            :aria-pressed="m.is_team_member"
            @click="toggleTeam(m)"
          >
            {{ m.is_team_member ? 'команда' : 'внешний' }}
          </button>
          <span v-else class="team-badge team read-only">команда</span>
        </td>
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
import { ParticipantsApi, type Participant } from '@/api/participants'
import { ApiError, errorMessage } from '@/api/client'
import { ASSIGNABLE_ROLES, ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'

// Members table. Each row's role select and team toggle PATCH optimistically — the row updates
// as soon as the server echoes back. The owner row is cosmetic-only; "yourself" row hides the
// remove action, both guards are mirrored on the server.
type AssignableRole = Exclude<ProjectRole, 'owner'>

const props = defineProps<{
  slug: string
  members: Participant[]
  meId: number
}>()
const emit = defineEmits<{
  changed: [member: Participant]
  removed: [userId: number]
}>()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const roleOptions = computed(() => ASSIGNABLE_ROLES.map((value) => ({ value, label: ROLE_LABELS[value] })))

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

async function toggleTeam(member: Participant) {
  const next = !member.is_team_member
  try {
    const res = await ParticipantsApi.update(props.slug, member.user_id, { is_team_member: next })
    emit('changed', res.data)
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
