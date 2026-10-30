import { request } from './client'
import type { Item, ProjectRole } from './types'
import type { Invitation } from './participants'

/** A pending «Запросить доступ» card on the participants page. */
export interface AccessRequest {
  id: number
  name: string
  email: string
  message: string | null
  /** Asked while logged in: the address belongs to an account. */
  has_account: boolean
  created_at: string
}

export const AccessRequestsApi = {
  // the gate of a private board: always 202 with the address the answer will go to;
  // for a logged-in visitor the server takes name and email from the account
  create(slug: string, body: { name?: string; email?: string; message?: string }) {
    return request<Item<{ email: string }>>(`/public/projects/${encodeURIComponent(slug)}/access-requests`, { method: 'POST', body })
  },

  // approve with a role: the server issues an invitation and mails its link, the response is that invitation
  approve(slug: string, id: number, role: Exclude<ProjectRole, 'owner'>) {
    return request<Item<Invitation>>(`/admin/boards/${slug}/access-requests/${id}/approve`, { method: 'POST', body: { role } })
  },

  // reject with an optional reason (it goes into the letter); the address can't ask again for a day
  reject(slug: string, id: number, body: { reason?: string } = {}) {
    return request<void>(`/admin/boards/${slug}/access-requests/${id}/reject`, { method: 'POST', body })
  },
}