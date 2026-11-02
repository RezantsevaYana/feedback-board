<template>
  <BoardWorkspace :slug="slug" page="access" :title="justCreated ? `Доска «${boardName}» готова` : undefined">
    <template v-if="justCreated" #lead>
      Осталось решить, кто её видит, и поделиться ссылкой — или поставить виджет на сайт.
    </template>

    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="settings && embed && people">
      <section id="mode" class="board-settings-section">
        <h2>Кто видит доску</h2>
        <AccessMode :settings="settings" @saved="onModeSaved" @pending="pending = $event" />
      </section>

      <!-- while another mode is picked but not applied, the rest is muted and inert: it changes after the switch -->
      <p v-if="pending" class="board-access-pending">
        {{ open ? 'Блоки ниже изменятся, когда доска станет закрытой' : 'Блоки ниже изменятся, когда доска станет открытой' }}
      </p>
      <div class="board-access-rest" :class="{ pending }" :inert="pending">
        <section id="link" class="board-settings-section">
          <h2>Ссылка на доску</h2>
          <p class="note">{{ open ? 'Откроет любой. Поделитесь ею в чате или письме.' : 'Откроют участники; остальные увидят экран «Запросить доступ».' }}</p>
          <!-- until the email is confirmed the API answers visitors 403 (EnsureProjectPublished) -->
          <p v-if="auth.me && !auth.me.email_verified" class="board-access-warn">
            Пока почта не подтверждена, ссылка и виджет не работают для посетителей: они увидят «Доска ещё не открыта».
          </p>
          <pre class="board-access-code"><code>{{ embed.direct_url }}</code></pre>
          <CopyButton :value="embed.direct_url" label="Скопировать ссылку" class="board-access-copy" />
        </section>

        <!-- open board: the widget with its domains and colour, then the team -->
        <template v-if="open">
          <section id="widget" class="board-settings-section">
            <h2>Виджет на сайте</h2>
            <p class="note">Кнопка «Обратная связь» на сайте продукта. Вставьте тег перед закрывающим <code class="mono">&lt;/body&gt;</code>.</p>
            <pre class="board-access-code"><code>{{ embed.script_tag }}</code></pre>
            <CopyButton :value="embed.script_tag" label="Скопировать код" class="board-access-copy" />
            <h3 class="board-access-subhead">Домены</h3>
            <SettingsDomains :settings="settings" @saved="onDomainsSaved" />
          </section>

          <WidgetColorSection :slug="slug" :color="embed.widget_color" @saved="embed.widget_color = $event" />

          <section id="team" class="board-settings-section">
            <div class="participants-head-row">
              <h2>Команда</h2>
              <button type="button" class="btn btn-add" @click="showInvite = true">+ Добавить в команду</button>
            </div>
            <p class="note">Те, кто разбирает посты и настраивает доску. Гостям приглашение не нужно: доска открыта по ссылке.</p>
            <h3 v-if="people.invitations.length" class="board-access-subhead">Ждут приглашения</h3>
            <PendingInvitationsList v-if="people.invitations.length" :slug="slug" :invitations="people.invitations" @cancel="onInviteCancelled" />
            <ParticipantsTable :slug="slug" :members="people.members" :me-id="auth.me?.id ?? 0" team-only @changed="onMemberChanged" @removed="onMemberRemoved" />
          </section>
        </template>

        <!-- closed board: who asks to get in, who's on it; the widget isn't available -->
        <template v-else>
          <section v-if="people.requests.length" id="requests" class="board-settings-section">
            <h2>Запросы на доступ<span class="count-badge">{{ people.requests.length }}</span></h2>
            <p class="note">Просят пустить на доску. После одобрения человек получит письмо со ссылкой на вход.</p>
            <AccessRequestsList :key="slug" :slug="slug" :requests="people.requests" @decided="onRequestDecided" />
          </section>

          <section id="participants" class="board-settings-section">
            <div class="participants-head-row">
              <h2>Участники</h2>
              <button type="button" class="btn btn-add" @click="showInvite = true">+ Пригласить</button>
            </div>
            <p class="note">Доску видят только люди из этого списка. Один человек — одна роль, что может каждая, видно при выборе.</p>
            <h3 v-if="people.invitations.length" class="board-access-subhead">Ждут приглашения</h3>
            <PendingInvitationsList v-if="people.invitations.length" :slug="slug" :invitations="people.invitations" @cancel="onInviteCancelled" />
            <ParticipantsTable :slug="slug" :members="people.members" :me-id="auth.me?.id ?? 0" @changed="onMemberChanged" @removed="onMemberRemoved" />
          </section>

          <section id="widget" class="board-settings-section">
            <h2>Виджет на сайте</h2>
            <div class="board-access-off">
              <p><strong>Виджет недоступен.</strong> Он работает только у открытой доски. Если виджет уже стоит на сайте, код лучше убрать.</p>
            </div>
            <!-- what the widget shows a guest now: the same words as widget/WidgetApp.vue -->
            <div class="board-access-guest" aria-label="Что увидит гость в виджете">
              <span class="board-access-guest-caption">Что увидит гость в виджете</span>
              <strong>Доска закрыта</strong>
              <span>Предложения здесь больше не принимаются. Участники могут открыть доску по ссылке.</span>
            </div>
          </section>
        </template>
      </div>
    </template>

    <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>

    <InviteModal v-if="showInvite" :slug="slug" :team-only="open" @close="showInvite = false" @sent="onInvitesSent" />
  </BoardWorkspace>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { type BoardSettings, BoardsApi, type EmbedInfo } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { type Invitation, type Participant, ParticipantsApi, type ParticipantsPayload } from '@/api/participants'
import { useAuthStore } from '@/store/auth/useAuthStore'
import CopyButton from '@/shared/ui/CopyButton.vue'
import AccessMode from './components/AccessMode.vue'
import AccessRequestsList from './components/AccessRequestsList.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import InviteModal from './components/InviteModal.vue'
import ParticipantsTable from './components/ParticipantsTable.vue'
import PendingInvitationsList from './components/PendingInvitationsList.vue'
import SettingsDomains from './components/SettingsDomains.vue'
import WidgetColorSection from './components/WidgetColorSection.vue'
import { boardLoadError } from './composables/boardLoadError'

// «Доступ» — who sees the board and how they get in. The mode (open / closed) is at the top and the rest shows only
// what works in it: an open board has the link, the widget (code, domains, colour) and its team; a closed one has
// the link, access requests, participants and a note instead of the widget (TEST.md п. 11, 27; prototype
// frontend/demo/board-pages-prototype.html). The lists are patched locally as rows change, no refetch.
const route = useRoute()
const auth = useAuthStore()

const slug = computed(() => String(route.params.slug ?? ''))
const justCreated = computed(() => route.query.created === '1')
const settings = ref<BoardSettings | null>(null)
const embed = ref<EmbedInfo | null>(null)
const people = ref<ParticipantsPayload | null>(null)
const loading = ref(true)
const loadError = ref('')
const showInvite = ref(false)
// another mode is picked on «Кто видит доску» but not applied yet
const pending = ref(false)
const open = computed(() => settings.value?.visibility !== 'private')
const boardName = computed(() => settings.value?.name ?? auth.me?.projects.find((b) => b.slug === slug.value)?.name ?? slug.value)

watch(slug, load, { immediate: true })

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [s, e, p] = await Promise.all([BoardsApi.show(slug.value), BoardsApi.embed(slug.value), ParticipantsApi.list(slug.value)])
    settings.value = s.data
    embed.value = e.data
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

// the mode changed: what the page shows changes with it (the team vs participants), so the lists are reloaded
async function onModeSaved(next: BoardSettings) {
  pending.value = false
  settings.value = next
  if (embed.value) embed.value.visibility = next.visibility
  const p = await ParticipantsApi.list(slug.value).catch(() => null)
  if (p) people.value = p.data
}

function onDomainsSaved(next: BoardSettings) {
  settings.value = next
  if (embed.value) embed.value.allowed_origins = next.allowed_origins
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

// an approved request turns into a pending invitation (it's the letter's link), a rejected one just goes;
// the badge in the menu and in the hub follows the list
function onRequestDecided(id: number, invitation: Invitation | null) {
  if (!people.value) return
  people.value.requests = people.value.requests.filter((r) => r.id !== id)
  if (invitation) onInvitesSent([invitation])
  auth.setPendingRequests(slug.value, people.value.requests.length)
}

function onInvitesSent(created: Invitation[]) {
  if (!people.value) return
  // created rows go to the top, replacing any pending slot the server updated in place
  const createdIds = new Set(created.map((i) => i.id))
  people.value.invitations = [...created, ...people.value.invitations.filter((i) => !createdIds.has(i.id))]
}
</script>
