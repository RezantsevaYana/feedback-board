import { request } from './client'
import type { Item, Status } from './types'

const BASE_URL = '/admin'

export const StatusesApi = {
  // add a kanban column to the project
  create(slug: string, name: string) {
    return request<Item<Status>>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/statuses`, { method: 'POST', body: { name } })
  },

  // rename a column (works for custom and system — переименовать «Готово» в «Выпущено»)
  rename(id: number, name: string) {
    return request<Item<Status>>(`${BASE_URL}/statuses/${id}`, { method: 'PATCH', body: { name } })
  },

  // remove a custom column; its posts move back to the default («Новое»)
  remove(id: number) {
    return request<void>(`${BASE_URL}/statuses/${id}`, { method: 'DELETE' })
  },
}
