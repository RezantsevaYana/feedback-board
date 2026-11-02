import { request } from './client'
import type { BoardVisibility } from './boards'
import type { Collection, ProjectRole } from './types'

const BASE_URL = '/admin/platform'

/** A board in «Админка сервиса»: any board of the service, the trashed ones too. */
export interface PlatformBoard {
  id: number
  name: string
  slug: string
  visibility: BoardVisibility
  /** null for legacy boards made from the console (project:create) */
  owner: { id: number; name: string; email: string } | null
  /** false while the owner hasn't confirmed the email: the board is closed to the public */
  published: boolean
  posts_count: number
  members_count: number
  last_post_at: string | null
  created_at: string
  /** set when the board is in the trash */
  deleted_at: string | null
}

/** An account in «Админка сервиса» with the boards it's on. */
export interface PlatformUser {
  id: number
  name: string
  email: string
  email_verified: boolean
  is_superadmin: boolean
  boards: { name: string; slug: string; role: ProjectRole }[]
  created_at: string
}

export const PlatformApi = {
  // every board of the service, newest first, with its owner and counts
  boards() {
    return request<Collection<PlatformBoard>>(`${BASE_URL}/boards`)
  },

  // every account, newest first, with its boards and roles
  users() {
    return request<Collection<PlatformUser>>(`${BASE_URL}/users`)
  },
}