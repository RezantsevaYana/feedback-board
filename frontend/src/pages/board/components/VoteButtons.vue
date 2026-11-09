<template>
  <span class="votes" :class="{ sm: small, stacked }">
    <!-- team on a touch screen (no hover there): the latest voters as faces, one tap target that opens the whole
         list in a bottom sheet. With a mouse the list comes from hovering the buttons instead -->
    <button
      v-if="canSeeVoters && faces.length && !canHover"
      type="button"
      class="voter-faces"
      :aria-label="`Кто голосовал: ${total}`"
      @click.stop="openVoters(post.likes_count >= post.dislikes_count ? 'like' : 'dislike')"
    >
      <span v-for="(f, i) in faces" :key="i" class="voter-face" :class="[`tone-${i}`, { guest: !f.account && !f.name }]" aria-hidden="true">{{ f.initials }}</span>
      <span v-if="rest > 0" class="voter-faces-rest" aria-hidden="true">+{{ rest }}</span>
    </button>
    <span
      v-for="kind in KINDS"
      :key="kind"
      class="vote-wrap"
      @mouseenter="hoverStart(kind, $event)"
      @mouseleave="hoverEnd"
    >
      <button
        type="button"
        class="vote"
        :class="{ dislike: kind === 'dislike' }"
        :aria-pressed="post.my_vote === kind"
        :aria-label="label(kind)"
        :title="voteBlockedTitle"
        :aria-disabled="!canVote"
        @click.stop="onClick(kind)"
      >
        <span class="emo" aria-hidden="true">{{ kind === 'like' ? '👍' : '💩' }}</span>
        <span>{{ kind === 'like' ? post.likes_count : post.dislikes_count }}</span>
      </button>
      <!-- team, with a mouse: who voted this way, after a short pause on the button -->
      <span v-if="popKind === kind" class="voters-pop" :class="{ below: popBelow }" role="dialog" :aria-label="`Кто голосовал: ${kind === 'like' ? 'нравится' : 'не нравится'}`">
        <span class="voters-pop-title">{{ kind === 'like' ? 'Нравится' : 'Не нравится' }} · {{ kind === 'like' ? post.likes_count : post.dislikes_count }}</span>
        <span v-if="popVoters === null" class="voters-pop-note">Загружаем…</span>
        <span v-else-if="!popVoters.length" class="voters-pop-note">Пока никто</span>
        <template v-else>
          <span v-for="(v, i) in popVoters.slice(0, POP_SIZE)" :key="i" class="voters-pop-row">
            {{ v.name }}<span v-if="!v.account" class="voters-pop-guest">гость</span>
          </span>
          <span v-if="popVoters.length > POP_SIZE" class="voters-pop-note">и ещё {{ popVoters.length - POP_SIZE }}</span>
          <button type="button" class="voters-pop-all" @click.stop="openVoters(kind)">Весь список ›</button>
        </template>
      </span>
    </span>
  </span>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Post, Voter, VoteValue } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useVotersStore } from '@/store/modals/useVotersStore'
import { initials } from '@/shared/utils/format'

// 👍 / 💩: two buttons for everyone, the count inside; clicking the active one cancels it, the other one switches.
// The triage team (owner / admin / manager) also sees who voted: with a mouse a short list over the button after
// a pause (and «Весь список ›» from it); on a touch screen the latest voters as faces in front of the buttons,
// a tap opens the whole list (VotersModal, a bottom sheet on a phone).
// A reader on a closed board sees the counts but can't vote: the buttons are disabled, the title says why.
const props = defineProps<{
  post: Post
  small?: boolean
  stacked?: boolean
}>()
const board = useBoardStore()
const voters = useVotersStore()

const KINDS: VoteValue[] = ['like', 'dislike']
const READ_ONLY = 'Ваша роль на доске — читатель: можно смотреть, голосовать нельзя'
const GUEST_CANT_VOTE = 'Войдите в аккаунт, чтобы проголосовать'
const POP_SIZE = 8
const canVote = computed(() => board.canVote)
// why the buttons are disabled: a reader on a closed board, or a guest on an open one where the owner hid voting
// from guests (test.md п. 40). Guests see a prompt that names the way out, readers see a dead-end label.
const voteBlockedTitle = computed(() => {
  if (canVote.value) return undefined
  return board.me ? READ_ONLY : GUEST_CANT_VOTE
})
const canSeeVoters = computed(() => board.role === 'owner' || board.role === 'admin' || board.role === 'manager')

const total = computed(() => props.post.likes_count + props.post.dislikes_count)
const faces = computed(() => (props.post.voters_preview ?? []).map((v) => ({
  account: v.account,
  name: v.name,
  initials: v.name ? initials(v.name, null) : '?',
})))
// the faces are the latest few; the rest is the count. Without any face (old counters with no vote rows) no stack
const rest = computed(() => Math.max(0, total.value - faces.value.length))

function label(kind: VoteValue): string {
  const mine = props.post.my_vote === kind
  if (kind === 'like') return mine ? 'Убрать лайк' : 'Нравится'
  return mine ? 'Убрать дизлайк' : 'Не нравится'
}

function openVoters(kind: VoteValue) {
  clearTimeout(timer)
  popKind.value = null
  voters.open(props.post.id, kind, props.post.title)
}

// the hover list: only for the team and only with a real pointer (a phone opens the sheet by the faces)
const canHover = typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine)').matches
const popKind = ref<VoteValue | null>(null)
const popBelow = ref(false)
const popVoters = ref<Voter[] | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

function hoverStart(kind: VoteValue, e: MouseEvent) {
  if (!canSeeVoters.value || !canHover) return
  const target = e.currentTarget as HTMLElement
  clearTimeout(timer)
  // back on the same button before it closed: keep the list as it is
  if (popKind.value === kind) return
  // a pause, so the list doesn't flash while the mouse just crosses the board
  timer = setTimeout(async () => {
    // near the top of the screen there's no room above: the list opens under the button
    popBelow.value = target.getBoundingClientRect().top < 240
    popKind.value = kind
    popVoters.value = null
    const list = await voters.load(props.post.id).catch(() => [])
    if (popKind.value === kind) popVoters.value = list.filter((v) => v.kind === kind)
  }, 300)
}
// leaving the button hides the list a moment later, so the pointer can move onto it and press «Весь список»
function hoverEnd() {
  clearTimeout(timer)
  timer = setTimeout(() => (popKind.value = null), 150)
}
onBeforeUnmount(() => clearTimeout(timer))
</script>
