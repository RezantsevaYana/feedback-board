import { request } from './client'
import type { Collection, Item, ProjectRole } from './types'

const BASE_URL = '/admin/boards'

/** A board in the admin hub list — summary + metrics. Every board the user is on, not only their own. */
export interface Board {
  id: number
  name: string
  slug: string
  visibility: BoardVisibility
  created_at: string
  queue_count: number
  last_post_at: string | null
  members_count: number
  /** the current user's role here: owner / admin go to «Я веду», the rest to «Я участник» */
  role: ProjectRole
  /** the new-board onboarding step to continue from (2–6); null — nothing to continue */
  onboarding_step: number | null
}

/** A board in the trash: listed on the hub until purge_at, then removed for good. */
export interface TrashedBoard {
  id: number
  name: string
  slug: string
  deleted_at: string
  purge_at: string
}

/** Response from GET /boards/{slug}/embed — ready-to-paste script and the direct link. */
export interface EmbedInfo {
  script_tag: string
  widget_url: string
  direct_url: string
  allowed_origins: string[]
  /** «#rrggbb» or null for the service's green */
  widget_color: string | null
  /** custom launcher button label or null for the default «Обратная связь» */
  widget_launcher_text: string | null
  visibility: BoardVisibility
}

/** public → everyone with the URL. private → only project_user members. Switches both ways. */
export type BoardVisibility = 'public' | 'private'

/** GET/PATCH /boards/{slug} — what the settings page edits. Slug is read-only after creation. */
export interface BoardSettings {
  id: number
  name: string
  slug: string
  visibility: BoardVisibility
  allowed_origins: string[]
  widget_color: string | null
  widget_launcher_text: string | null
  /** show the board page in search engines; false → noindex meta + robots.txt Disallow (test.md п. 19) */
  search_indexable: boolean
  /** test.md п. 40 — guests on an open board: may they post / vote. false → «Войдите, чтобы …» */
  guests_can_post: boolean
  guests_can_vote: boolean
  /** how long a deleted board stays in the trash (feedback.trash_retention_days) */
  trash_retention_days: number
  /** visible posts and members, for the «Доступ» warning */
  posts_count: number
  members_count: number
  /** the new-board onboarding step to continue from (2–6); null — nothing to continue */
  onboarding_step: number | null
}

/** Live slug check — the create wizard calls this with debounce as the user types. */
export type SlugCheckReason = 'format' | 'reserved' | 'taken' | null
export interface SlugCheck {
  available: boolean
  reason: SlugCheckReason
}

export const BoardsApi = {
  // every board the user is on with their role, newest first, plus their own ones in the trash
  list() {
    return request<Collection<Board> & { trashed: TrashedBoard[] }>(BASE_URL)
  },

  // create a self-serve board — origins can be omitted when the owner wants direct-link only
  create(body: { name: string; slug: string; origins?: string[] }) {
    return request<Item<Board>>(BASE_URL, { method: 'POST', body })
  },

  // name, slug and widget domains for the settings page
  show(slug: string) {
    return request<Item<BoardSettings>>(`${BASE_URL}/${slug}`)
  },

  // rename the board, replace its widget domains, set the widget colour (null = default), set the
  // widget launcher text (null = default «Обратная связь») or flip visibility — send only what changed
  update(
    slug: string,
    body: {
      name?: string
      origins?: string[]
      widget_color?: string | null
      widget_launcher_text?: string | null
      search_indexable?: boolean
      guests_can_post?: boolean
      guests_can_vote?: boolean
      onboarding_step?: number | null
      visibility?: BoardVisibility
    },
  ) {
    return request<Item<BoardSettings>>(`${BASE_URL}/${slug}`, { method: 'PATCH', body })
  },

  // to the trash — the owner types the slug as confirmation
  destroy(slug: string, confirm: string) {
    return request<Item<TrashedBoard>>(`${BASE_URL}/${slug}`, { method: 'DELETE', body: { confirm } })
  },

  // back from the trash with its posts, categories and columns
  restore(slug: string) {
    return request<Item<Board>>(`${BASE_URL}/${slug}/restore`, { method: 'POST' })
  },

  // widget script + direct board URL for the "how to connect" screen
  embed(slug: string) {
    return request<Item<EmbedInfo>>(`${BASE_URL}/${slug}/embed`)
  },

  // live uniqueness/format check during slug entry
  checkSlug(slug: string) {
    return request<Item<SlugCheck>>(`${BASE_URL}/check-slug`, { method: 'POST', body: { slug } })
  },
}
