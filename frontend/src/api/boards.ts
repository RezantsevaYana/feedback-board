import { request } from './client'
import type { Collection, Item } from './types'

const BASE_URL = '/admin/boards'

/** A board in the admin hub list — summary + metrics. */
export interface Board {
  id: number
  name: string
  slug: string
  created_at: string
  queue_count: number
  last_post_at: string | null
}

/** Response from GET /boards/{slug}/embed — ready-to-paste script and the direct link. */
export interface EmbedInfo {
  script_tag: string
  widget_url: string
  direct_url: string
  allowed_origins: string[]
}

/** GET/PATCH /boards/{slug} — what the settings page edits. Slug is read-only after creation. */
export interface BoardSettings {
  id: number
  name: string
  slug: string
  allowed_origins: string[]
}

/** Live slug check — the create wizard calls this with debounce as the user types. */
export type SlugCheckReason = 'format' | 'reserved' | 'taken' | null
export interface SlugCheck {
  available: boolean
  reason: SlugCheckReason
}

export const BoardsApi = {
  // boards owned by the current admin, newest first
  list() {
    return request<Collection<Board>>(BASE_URL)
  },

  // create a self-serve board — origins can be omitted when the owner wants direct-link only
  create(body: { name: string; slug: string; origins?: string[] }) {
    return request<Item<Board>>(BASE_URL, { method: 'POST', body })
  },

  // name, slug and widget domains for the settings page
  show(slug: string) {
    return request<Item<BoardSettings>>(`${BASE_URL}/${slug}`)
  },

  // rename the board or replace its widget domains — send only what the section changed
  update(slug: string, body: { name?: string; origins?: string[] }) {
    return request<Item<BoardSettings>>(`${BASE_URL}/${slug}`, { method: 'PATCH', body })
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
