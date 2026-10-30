<template>
  <ul class="access-requests">
    <li v-for="req in requests" :key="req.id" class="access-request">
      <div class="access-request-who">
        <div class="access-request-name">{{ req.name }}</div>
        <div class="access-request-meta">
          {{ req.email }}<template v-if="req.has_account"> · есть аккаунт</template> · {{ fmtTimeAgo(req.created_at) }}
        </div>
      </div>
      <p v-if="req.message" class="access-request-message">{{ req.message }}</p>

      <div v-if="rejecting === req.id" class="access-request-reject">
        <TextAreaComponent
          v-model="reason"
          label="Причина"
          hint="по желанию, её увидят в письме"
          rows="2"
          maxlength="500"
        />
        <div class="access-request-actions">
          <button type="button" class="btn btn-danger" :disabled="busyId === req.id" :aria-busy="busyId === req.id" @click="reject(req)">
            Отклонить
          </button>
          <button type="button" class="btn btn-ghost" :disabled="busyId === req.id" @click="rejecting = null">Отмена</button>
        </div>
      </div>

      <div v-else class="access-request-actions">
        <SelectComponent
          :model-value="roles[req.id] ?? 'voter'"
          :options="roleOptions"
          @update:model-value="(role) => (roles[req.id] = role as Assignable)" class="access-request-role" aria-label="Роль на доске" />
        <button type="button" class="btn btn-primary" :disabled="busyId === req.id" :aria-busy="busyId === req.id" @click="approve(req)">
          Одобрить
        </button>
        <button type="button" class="btn" :disabled="busyId === req.id" @click="startReject(req)">Отклонить</button>
      </div>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { type AccessRequest, AccessRequestsApi } from '@/api/accessRequests'
import { errorMessage } from '@/api/client'
import type { Invitation } from '@/api/participants'
import { ASSIGNABLE_ROLES, ROLE_HINTS, ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { fmtTimeAgo } from '@/shared/utils/format'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// «Запросы на доступ»: approve inline with a role (Голосующий by default, the usual one for someone from outside),
// or reject with an optional reason that goes into the letter (the address then can't ask again for a day).
// The parent drops the card on `decided`; an approval also hands over the invitation it produced.
const props = defineProps<{
  slug: string
  requests: AccessRequest[]
}>()
const emit = defineEmits<{
  decided: [id: number, invitation: Invitation | null]
}>()
const notification = useNotificationStore()

type Assignable = Exclude<ProjectRole, 'owner'>
const roleOptions = ASSIGNABLE_ROLES.map((value) => ({ value, label: ROLE_LABELS[value], hint: ROLE_HINTS[value] }))
const roles = reactive<Record<number, Assignable>>(Object.fromEntries(props.requests.map((r) => [r.id, 'voter'])))
const busyId = ref<number | null>(null)
const rejecting = ref<number | null>(null)
const reason = ref('')

async function approve(req: AccessRequest) {
  const role = roles[req.id] ?? 'voter'
  busyId.value = req.id
  try {
    const res = await AccessRequestsApi.approve(props.slug, req.id, role)
    notification.success(`${req.name} получит письмо со ссылкой. Роль: ${ROLE_LABELS[role]}`)
    emit('decided', req.id, res.data)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyId.value = null
  }
}

function startReject(req: AccessRequest) {
  reason.value = ''
  rejecting.value = req.id
}

async function reject(req: AccessRequest) {
  busyId.value = req.id
  try {
    await AccessRequestsApi.reject(props.slug, req.id, { reason: reason.value.trim() || undefined })
    notification.success(`Запрос отклонён, ${req.email} получит письмо. Повторно попроситься можно через сутки`)
    rejecting.value = null
    emit('decided', req.id, null)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busyId.value = null
  }
}
</script>