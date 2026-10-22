// Shapes of the Laravel API (backend/app/Http/Resources).

/** slug системных статусов — для веток «попросить ответ команды» и «запретить удаление». */
export type SystemStatusSlug = 'new' | 'done' | 'rejected'

export type PostPriority = 'low' | 'medium' | 'high' | 'critical'
export type VoteValue = 'like' | 'dislike'

/** Configured by the team per project («Баг», «Идея», «Доставка»…). */
export interface Category {
  id: number
  name: string
  // Palette slug chosen by the admin. null = legacy rows that predate the
  // colour feature; the tag falls back to the neutral --tag token.
  color: PaletteColor | null
}

/** Palette slugs used by category tags and status pills. CSS tokens live in scss/base/_tokens.scss. */
export const PALETTE_COLORS = ['red', 'orange', 'yellow', 'green', 'blue', 'pink'] as const
export type PaletteColor = (typeof PALETTE_COLORS)[number]

/** Колонка канбана. Три системных создаются вместе с проектом, остальные — команда сама. */
export interface Status {
  id: number
  slug: string
  name: string
  position: number
  is_system: boolean
  // Palette slug chosen by the admin; drives pill colour and column accent.
  color: PaletteColor | null
}

export interface Attachment {
  id: number
  url: string
  original_name: string
}

export interface Post {
  id: number
  category: Category | null
  title: string
  description: string
  author_name: string | null
  status: Status
  likes_count: number
  dislikes_count: number
  my_vote: VoteValue | null
  team_comment: string | null
  attachments?: Attachment[]
  attachments_count?: number
  created_at: string
  status_changed_at: string | null
  // team mode only
  priority?: PostPriority | null
  is_hidden?: boolean
}

export interface Project {
  name: string
  slug: string
  categories: Category[]
  statuses: Status[]
}

export interface Me {
  id: number
  name: string
  email: string
  projects: Array<{ id: number; name: string; slug: string }>
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface Item<T> {
  data: T
}

export interface VoteState {
  likes_count: number
  dislikes_count: number
  my_vote: VoteValue | null
}
