<template>
  <BoardWorkspace :slug="slug" page="access">

    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="settings && people">
      <!-- Variant B from the prototype (frontend/demo/settings-rework-prototype.html): one outer card, inside —
           the mode picker as a direct child, then sub-blocks separated by hairlines. Keeps the page compact on
           desktop and on phone it reads as a single list of sections. -->
      <section class="board-settings-section board-access-section">
        <AccessMode id="mode" :settings="settings" @saved="onModeSaved" @pending="pending = $event" />

        <!-- while another mode is picked but not applied, the rest is muted and inert: it changes after the switch -->
        <p v-if="pending" class="board-access-pending">
          {{ open ? 'Блоки ниже изменятся, когда доска станет закрытой' : 'Блоки ниже изменятся, когда доска станет открытой' }}
        </p>
        <div class="board-access-rest" :class="{ pending }" :inert="pending">
          <!-- open board: guests (TEST.md п. 40), search-engine toggle (п. 19), then the team (ссылка — в «Общем», виджет — в «Виджете», п. 42). -->
          <template v-if="open">
            <GuestsSection
              :slug="slug"
              :guests-can-post="settings.guests_can_post"
              :guests-can-vote="settings.guests_can_vote"
              @saved="onGuestsSaved"
            />
            <SearchIndexingSection :slug="slug" :search-indexable="settings.search_indexable" @saved="onSearchIndexableSaved" />

            <!-- on a phone it folds, the people count in its header; the requests above it never fold -->
            <FoldSection id="team" title="Команда" class="board-access-group" :summary="peopleSummary">
              <div class="participants-actions">
                <button type="button" class="btn btn-sm" @click="showInvite = true">+ Пригласить</button>
              </div>
              <p class="note">Те, кто разбирает посты и настраивает доску. Гостям приглашение не нужно: доска открыта по ссылке.</p>
              <ParticipantsTable
                :slug="slug"
                :members="people.members"
                :invitations="people.invitations"
                :me-id="auth.me?.id ?? 0"
                team-only
                @changed="onMemberChanged"
                @removed="onMemberRemoved"
                @invite-cancelled="onInviteCancelled"
              />
            </FoldSection>
          </template>

          <!-- closed board: who asks to get in, who's on it -->
          <template v-else>
            <div v-if="people.requests.length" id="requests" class="board-access-group">
              <h3>Запросы на доступ<span class="count-badge">{{ people.requests.length }}</span></h3>
              <p class="note">Просят пустить на доску. После одобрения доступ подтверждённого аккаунта откроется сразу. Остальные получат письмо с приглашением.</p>
              <AccessRequestsList :key="slug" :slug="slug" :requests="people.requests" @decided="onRequestDecided" />
            </div>

            <FoldSection id="participants" title="Участники" class="board-access-group" :summary="peopleSummary">
              <div class="participants-actions">
                <button type="button" class="btn btn-sm" @click="showInvite = true">+ Пригласить</button>
              </div>
              <p class="note">Доску видят только люди из этого списка. Один человек — одна роль, что может каждая, видно при выборе.</p>
              <ParticipantsTable
                :slug="slug"
                :members="people.members"
                :invitations="people.invitations"
                :me-id="auth.me?.id ?? 0"
                @changed="onMemberChanged"
                @removed="onMemberRemoved"
                @invite-cancelled="onInviteCancelled"
              />
            </FoldSection>
          </template>
        </div>
      </section>
    </template>

    <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>

    <InviteModal v-if="showInvite" :slug="slug" :team-only="open" @close="showInvite = false" @sent="onInvitesSent" />
  </BoardWorkspace>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { type Invitation, type Participant, ParticipantsApi, type ParticipantsPayload } from '@/api/participants'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { plural } from '@/shared/utils/format'
import FoldSection from '@/shared/ui/FoldSection.vue'
import AccessMode from './components/AccessMode.vue'
import AccessRequestsList from './components/AccessRequestsList.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import GuestsSection from './components/GuestsSection.vue'
import SearchIndexingSection from './components/SearchIndexingSection.vue'
import InviteModal from './components/InviteModal.vue'
import ParticipantsTable from './components/ParticipantsTable.vue'
import { boardLoadError } from './composables/boardLoadError'
import { useVisiblePolling } from '@/shared/composables/useVisiblePolling'
import { NO_CONFIGURE_TEXT, useLostAccess } from './composables/useLostAccess'

// «Доступ» — who sees the board and who's on it. The mode (open / closed) is at the top and the rest shows only
// what works in it: an open board has its team; a closed one has access requests and participants
// (TEST.md п. 11, 27). The link moved to «Общее» and the widget to «Виджет» (п. 42; the router sends the old
// /access#widget and #link there). The lists are patched locally as rows change, no refetch.
const route = useRoute()
const auth = useAuthStore()

const slug = computed(() => String(route.params.slug ?? ''))
const settings = ref<BoardSettings | null>(null)
const people = ref<ParticipantsPayload | null>(null)
const loading = ref(true)
const loadError = ref('')
const showInvite = ref(false)
// another mode is picked on «Кто видит доску» but not applied yet
const pending = ref(false)
const open = computed(() => settings.value?.visibility !== 'private')
// the folded team / participants block says how many: «3 человека · 1 ждёт приглашения»
const peopleSummary = computed(() => {
  const members = people.value?.members.length ?? 0
  const waiting = people.value?.invitations.length ?? 0
  return `${members} ${plural(members, 'человек', 'человека', 'человек')}`
    + (waiting ? ` · ${waiting} ${plural(waiting, 'ждёт', 'ждут', 'ждут')} приглашения` : '')
})

watch(slug, load, { immediate: true })
useLostAccess(slug, () => {
  settings.value = null
  loadError.value = NO_CONFIGURE_TEXT
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [s, p] = await Promise.all([BoardsApi.show(slug.value), ParticipantsApi.list(slug.value)])
    settings.value = s.data
    people.value = p.data
    // the page has the fresh count, the badges may be a few minutes old
    auth.setPendingRequests(slug.value, p.data.requests.length)
  } catch (e) {
    loadError.value = boardLoadError(e) || errorMessage(e)
  } finally {
    loading.value = false
  }
  // the sections exist only now: scroll to the one we arrived with (a menu link, the request letter)
  if (settings.value && route.hash) {
    await nextTick()
    document.querySelector(route.hash)?.scrollIntoView()
  }
}

// Refresh the actual rows as well as the sidebar badge, including invitations accepted elsewhere.
async function refreshPeople() {
  if (loading.value || !settings.value) return
  const currentSlug = slug.value
  const result = await ParticipantsApi.list(currentSlug)
  if (slug.value !== currentSlug) return
  people.value = result.data
  auth.setPendingRequests(currentSlug, result.data.requests.length)
}
useVisiblePolling(refreshPeople)

// the Поисковики section flipped the «Показывать в поисковиках» switch (test.md п. 19)
function onSearchIndexableSaved(next: boolean) {
  if (settings.value) settings.value = { ...settings.value, search_indexable: next }
}

// the Гости section flipped «кто может предлагать» / «кто может голосовать» (test.md п. 40)
function onGuestsSaved(next: { guestsCanPost: boolean; guestsCanVote: boolean }) {
  if (settings.value) settings.value = { ...settings.value, guests_can_post: next.guestsCanPost, guests_can_vote: next.guestsCanVote }
}

// the mode changed: what the page shows changes with it (the team vs participants), so the lists are reloaded
async function onModeSaved(next: BoardSettings) {
  pending.value = false
  settings.value = next
  const p = await ParticipantsApi.list(slug.value).catch(() => null)
  if (p) people.value = p.data
}

function onMemberChanged(next: Participant) {
  if (!people.value) return
  // the PATCH echo doesn't know the source — keep the one from the list
  people.value.members = people.value.members.map((m) => (m.user_id === next.user_id ? { ...next, source: next.source ?? m.source } : m))
}

function onMemberRemoved(userId: number) {
  if (!people.value) return
  people.value.members = people.value.members.filter((m) => m.user_id !== userId)
}

function onInviteCancelled(id: number) {
  if (!people.value) return
  people.value.invitations = people.value.invitations.filter((i) => i.id !== id)
}

// Approval either adds the verified applicant or sends an invitation; rejection removes the request;
// the badge in the menu and in the hub follows the list
function onRequestDecided(id: number, invitation: Invitation | null) {
  if (!people.value) return
  people.value.requests = people.value.requests.filter((r) => r.id !== id)
  if (invitation?.is_pending) onInvitesSent([invitation])
  void refreshPeople().catch(() => undefined)
  auth.setPendingRequests(slug.value, people.value.requests.length)
}

function onInvitesSent(created: Invitation[]) {
  if (!people.value) return
  // created rows go to the top, replacing any pending slot the server updated in place
  const createdIds = new Set(created.map((i) => i.id))
  people.value.invitations = [...created, ...people.value.invitations.filter((i) => !createdIds.has(i.id))]
  void refreshPeople().catch(() => undefined)
}
</script>
