import type { AccessRequest } from './accessRequests'
import { request } from './client'
import type { Collection, Item, ProjectRole } from './types'

const BASE_URL = '/admin/boards'

/** A person on the board, as returned by GET /boards/{slug}/participants. */
export interface Participant {
  user_id: number
  name: string
  email: string
  role: ProjectRole
  /** How they got here: created the board, accepted an invitation, or added before roles (CLI). Null in PATCH echoes. */
  source: ParticipantSource | null
}

export type ParticipantSource = 'created' | 'request' | 'invitation' | 'manual'

/** A pending or recently-accepted invitation. The token NEVER appears in API responses — it lives only in the letter. */
export interface Invitation {
  id: number
  email: string
  role: Exclude<ProjectRole, 'owner'>
  invited_by: { id: number; name: string }
  /** когда отправили — «приглашение отправлено DD MMM» в объединённой таблице участников (test.md п. 23) */
  sent_at: string
  expires_at: string
  accepted_at: string | null
  is_pending: boolean
}

/** The participants screen pulls all three lists in one call. */
export interface ParticipantsPayload {
  members: Participant[]
  invitations: Invitation[]
  /** «Запросы на доступ» waiting for a decision, oldest first. */
  requests: AccessRequest[]
}

/** One row of a bulk invite. */
export interface InviteRow {
  email: string
}

/** Why an email in a batch didn't become an invitation (currently only "already_member"). */
export interface InviteSkip {
  email: string
  reason: 'already_member'
}

export interface BulkInviteResult {
  data: Invitation[]
  skipped: InviteSkip[]
}

export const ParticipantsApi = {
  // list of members + pending invitations for the board
  list(slug: string) {
    return request<Item<ParticipantsPayload>>(`${BASE_URL}/${slug}/participants`)
  },

  // change the role; owner's row is immutable, see the controller
  update(slug: string, userId: number, body: { role: Exclude<ProjectRole, 'owner'> }) {
    return request<Item<Participant>>(`${BASE_URL}/${slug}/participants/${userId}`, { method: 'PATCH', body })
  },

  // remove from the board (owner can't be removed, you can't remove yourself)
  destroy(slug: string, userId: number) {
    return request<void>(`${BASE_URL}/${slug}/participants/${userId}`, { method: 'DELETE' })
  },
}

export const InvitationsApi = {
  // send the batch; the role is one for everyone
  create(slug: string, body: { role: Exclude<ProjectRole, 'owner'>; invitees: InviteRow[] }) {
    return request<BulkInviteResult>(`${BASE_URL}/${slug}/invitations`, { method: 'POST', body })
  },

  // rotate the token and send a fresh letter (same row in the DB)
  resend(slug: string, id: number) {
    return request<void>(`${BASE_URL}/${slug}/invitations/${id}/resend`, { method: 'POST' })
  },

  // cancel a pending invitation
  destroy(slug: string, id: number) {
    return request<void>(`${BASE_URL}/${slug}/invitations/${id}`, { method: 'DELETE' })
  },
}

/** What the acceptance page shows before the person clicks "Присоединиться". */
export interface InvitationPreview {
  email: string
  role: Exclude<ProjectRole, 'owner'>
  board: { slug: string; name: string }
  invited_by: { name: string | null }
  expires_at: string
  /** True = there's already an account on this email; the UI then suggests "войти чтобы принять". */
  account_exists: boolean
}

export interface AcceptResult {
  board: { slug: string; name: string }
  role: ProjectRole
}

export const InviteAcceptApi = {
  // public: read invitation metadata by token; 410 for expired/used, 404 for unknown
  show(token: string) {
    return request<Item<InvitationPreview>>(`/admin/invitations/${token}`)
  },

  // public: accept. For a new account — name and password are required.
  // 409 means an account already exists — the UI tells the person to log in and come back.
  accept(token: string, body: { name?: string; password?: string } = {}) {
    return request<Item<AcceptResult>>(`/admin/invitations/${token}/accept`, { method: 'POST', body })
  },
}

/** An invitation addressed to the logged-in user — «Вас приглашают» in the cabinet. */
export interface IncomingInvitation {
  id: number
  board: { slug: string; name: string }
  role: Exclude<ProjectRole, 'owner'>
  invited_by: { name: string | null }
  created_at: string
  expires_at: string
}

export const MyInvitationsApi = {
  // pending invitations to my email (empty until the email is confirmed)
  list() {
    return request<Collection<IncomingInvitation>>('/admin/me/invitations')
  },

  // join the board with the invited role
  accept(id: number) {
    return request<Item<AcceptResult>>(`/admin/me/invitations/${id}/accept`, { method: 'POST' })
  },

  // decline: the invitation is removed, the board's admins can invite again
  decline(id: number) {
    return request<void>(`/admin/me/invitations/${id}`, { method: 'DELETE' })
  },
}
