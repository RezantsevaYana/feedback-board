import { request } from './client'
import type { Item, PaletteColor, Status } from './types'

export interface StatusDeletionInfo {
  posts_count: number
  default_status_id: number | null
  statuses: Status[]
}
export interface StatusDeletionResult {
  default_status_id: number
  replacement_status_id: number
  moved_posts_count: number
}
const BASE_URL = '/admin'

export const StatusesApi = {
  // add a kanban column to the project; null colour leaves the pill on the neutral fallback
  create(slug: string, name: string, color: PaletteColor | null) {
    return request<Item<Status>>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/statuses`, { method: 'POST', body: { name, color } })
  },

  // rename a column or change its colour; null clears the colour back to the neutral pill
  update(id: number, name: string, color: PaletteColor | null) {
    return request<Item<Status>>(`${BASE_URL}/statuses/${id}`, { method: 'PATCH', body: { name, color } })
  },

  deletionInfo(id: number) {
    return request<Item<StatusDeletionInfo>>(`${BASE_URL}/statuses/${id}/deletion`)
  },

  remove(id: number, replacementStatusId: number) {
    return request<Item<StatusDeletionResult>>(`${BASE_URL}/statuses/${id}`, { method: 'DELETE', body: { replacement_status_id: replacementStatusId } })
  },

  setDefault(slug: string, statusId: number) {
    return request<Item<{ default_status_id: number }>>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/statuses/default`, { method: 'PATCH', body: { status_id: statusId } })
  },

  // reorder all kanban columns; `ids` is the complete list of this board's statuses
  reorder(slug: string, ids: number[]) {
    return request<{ data: Status[] }>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/statuses/reorder`, { method: 'PATCH', body: { ids } })
  },
}
