<template>
  <AppHeader />
  <main class="container base-page board-workspace-page">
    <BoardWorkspace :slug="slug" :name="boardName" page="participants">
      <p v-if="loading" class="note">Загружаем…</p>

      <template v-else-if="payload">
        <section class="board-settings-section participants-head">
          <div class="participants-head-row">
            <h2>Участники</h2>
            <button type="button" class="btn btn-primary" @click="showInvite = true">+ Пригласить</button>
          </div>
          <p class="note">Один человек — одна роль на доске. Бейдж «команда / внешний» живёт отдельно от роли.</p>
        </section>

        <section v-if="payload.invitations.length" class="board-settings-section">
          <h2>Ждут приглашения</h2>
          <PendingInvitationsList
            :slug="slug"
            :invitations="payload.invitations"
            @resend="onInviteResent"
            @cancel="onInviteCancelled"
          />
        </section>

        <section class="board-settings-section">
          <ParticipantsTable
            :slug="slug"
            :members="payload.members"
            :me-id="auth.me?.id ?? 0"
            @changed="onMemberChanged"
            @removed="onMemberRemoved"
          />
        </section>
      </template>

      <p v-else class="note">
        {{ loadError || 'Участников не удалось загрузить.' }}
        Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.
      </p>
    </BoardWorkspace>

    <InviteModal
      v-if="showInvite"
      :slug="slug"
      @close="showInvite = false"
      @sent="onInvitesSent"
    />
  </main>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ParticipantsApi, type Invitation, type Participant, type ParticipantsPayload } from '@/api/participants'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import ParticipantsTable from './components/ParticipantsTable.vue'
import PendingInvitationsList from './components/PendingInvitationsList.vue'
import InviteModal from './components/InviteModal.vue'
import { boardLoadError } from './composables/boardLoadError'

// Members + pending invitations on one page, framed by BoardWorkspace (same as Settings / Embed).
// The invite action spawns a modal; the parent keeps the loaded lists and patches them locally
// as rows change, so a successful operation doesn't need a full refetch.
const route = useRoute()
const auth = useAuthStore()

const slug = computed(() => String(route.params.slug ?? ''))
const payload = ref<ParticipantsPayload | null>(null)
const loading = ref(true)
const loadError = ref('')
const showInvite = ref(false)
const boardName = computed(() => auth.boardSummaries.find((b) => b.slug === slug.value)?.name ?? slug.value)

watch(slug, load, { immediate: true })

async function load() {
  loading.value = true
  loadError.value = ''
  payload.value = null
  try {
    const res = await ParticipantsApi.list(slug.value)
    payload.value = res.data
  } catch (e) {
    loadError.value = boardLoadError(e) || errorMessage(e)
  } finally {
    loading.value = false
  }
}

function onMemberChanged(next: Participant) {
  if (!payload.value) return
  payload.value.members = payload.value.members.map((m) => (m.user_id === next.user_id ? next : m))
}

function onMemberRemoved(userId: number) {
  if (!payload.value) return
  payload.value.members = payload.value.members.filter((m) => m.user_id !== userId)
}

function onInviteResent(id: number) {
  if (!payload.value) return
  // nothing visible to update — the server rotated the token, but the list row stays as it was
  void id
}

function onInviteCancelled(id: number) {
  if (!payload.value) return
  payload.value.invitations = payload.value.invitations.filter((i) => i.id !== id)
}

function onInvitesSent(created: Invitation[]) {
  if (!payload.value) return
  // created rows go to the top, replacing any pending slot the server updated in place
  const createdIds = new Set(created.map((i) => i.id))
  payload.value.invitations = [...created, ...payload.value.invitations.filter((i) => !createdIds.has(i.id))]
}
</script>
