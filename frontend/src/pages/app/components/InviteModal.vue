<template>
  <BaseModal label="Пригласить людей" @close="emit('close')">
    <form class="invite-modal" novalidate @submit.prevent="send">
      <div class="drawer-top">
        <h2>Пригласить людей</h2>
        <button type="button" class="icon-btn" aria-label="Отмена" @click="close"><AppIcon name="close" /></button>
      </div>

      <section v-if="step === 'input'" class="invite-step">
        <TextAreaComponent
          v-model="raw"
          label="Почты через запятую или по строкам"
          hint="до 100 за раз"
          rows="6"
          :error="rawError"
          placeholder="anna@acme.com, boris@acme.com&#10;client@othercorp.com"
          autofocus
        />
        <SelectComponent
          v-model="role"
          label="Роль для всей пачки"
          :options="roleOptions"
        />
        <div class="confirm-actions">
          <button type="button" class="btn" @click="close">Отмена</button>
          <button type="button" class="btn btn-primary" :disabled="!parsed.length" @click="toReview">
            Далее — {{ parsed.length }} {{ pluralHumans(parsed.length) }}
          </button>
        </div>
      </section>

      <section v-else class="invite-step">
        <p class="note">
          Отметьте «команда» для тех, кого считать сотрудниками.
          <template v-if="boardTeamDomain">
            Автоматически отмечены все, кто с домена
            <code class="mono">{{ boardTeamDomain }}</code>.
          </template>
          <template v-else>
            Домен команды у доски не задан — все считаются внешними, пока не отметите.
          </template>
        </p>
        <ul class="invite-rows">
          <li v-for="row in rows" :key="row.email" class="invite-row">
            <span class="invite-row-email">{{ row.email }}</span>
            <CheckboxComponent v-model="row.isTeam" label="команда" />
          </li>
        </ul>
        <div class="confirm-actions">
          <button type="button" class="btn" :disabled="sending" @click="step = 'input'">Назад</button>
          <button type="submit" class="btn btn-primary" :disabled="sending" :aria-busy="sending">
            {{ sending ? 'Отправляем…' : `Отправить ${rows.length} ${pluralLetters(rows.length)}` }}
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
import { ASSIGNABLE_ROLES, ROLE_LABELS, type ProjectRole } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import CheckboxComponent from '@/shared/ui/CheckboxComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// Two-step flow:
//   1. Textarea with emails + one role for the whole batch.
//   2. One row per email with a "команда / внешний" checkbox — defaulted from the board's team_domain,
//      overridable per row before the single POST that creates everything.
// The server dedupes already-members into "skipped"; we surface them as an info toast.
type AssignableRole = Exclude<ProjectRole, 'owner'>

const props = defineProps<{
  slug: string
}>()
const emit = defineEmits<{
  close: []
  sent: [created: Invitation[]]
}>()

const auth = useAuthStore()
const notification = useNotificationStore()
const closeModal = inject<() => void>('baseModal:close')

// placeholder for team_domain — we don't have it on the client yet (slice 2 of the plan). Null → no auto-team.
const boardTeamDomain = computed<string | null>(() => null)

const roleOptions = ASSIGNABLE_ROLES.map((value) => ({ value, label: ROLE_LABELS[value] }))
const role = ref<AssignableRole>('voter')

const raw = ref('')
const rawError = ref('')
const parsed = computed(() => parseEmails(raw.value))

interface Row {
  email: string
  isTeam: boolean
}

const step = ref<'input' | 'review'>('input')
const rows = ref<Row[]>([])
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

function toReview() {
  if (!parsed.value.length) {
    rawError.value = 'Добавьте хотя бы одну валидную почту.'
    return
  }
  rawError.value = ''
  const domain = boardTeamDomain.value?.toLowerCase() ?? null
  rows.value = parsed.value.map((email) => ({
    email,
    isTeam: domain ? email.split('@')[1]?.toLowerCase() === domain : false,
  }))
  step.value = 'review'
}

async function send() {
  if (!rows.value.length || sending.value) return
  sending.value = true
  try {
    const result = await InvitationsApi.create(props.slug, {
      role: role.value,
      invitees: rows.value.map((r) => ({ email: r.email, is_team_member: r.isTeam })),
    })
    const sentCount = result.data.length
    const skipped = result.skipped.length
    if (sentCount) {
      notification.success(
        `Отправлено ${sentCount} ${pluralLetters(sentCount)}`
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
  closeModal?.()
}

function pluralHumans(n: number) {
  if (n % 10 === 1 && n % 100 !== 11) return 'человек'
  if ([2, 3, 4].includes(n % 10) && ![12, 13, 14].includes(n % 100)) return 'человека'
  return 'человек'
}

function pluralLetters(n: number) {
  if (n % 10 === 1 && n % 100 !== 11) return 'письмо'
  if ([2, 3, 4].includes(n % 10) && ![12, 13, 14].includes(n % 100)) return 'письма'
  return 'писем'
}

void auth
</script>
