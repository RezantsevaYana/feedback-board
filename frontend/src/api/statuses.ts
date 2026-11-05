import { request } from './client'
import type { Item, PaletteColor, Status } from './types'

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

  // remove a custom column; its posts move back to the default («Новое»)
  remove(id: number) {
    return request<void>(`${BASE_URL}/statuses/${id}`, { method: 'DELETE' })
  },

  // reorder custom columns; `ids` is the full list of this project's custom statuses in the new order
  reorder(slug: string, ids: number[]) {
    return request<{ data: Status[] }>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/statuses/reorder`, { method: 'PATCH', body: { ids } })
  },
}
