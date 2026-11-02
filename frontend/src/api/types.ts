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
  /** team only: the latest voters (up to three) for the card's faces */
  voters_preview?: VoterPreview[]
}

export interface Project {
  name: string
  slug: string
  /** false until the owner confirms the email; only the team ever gets it, everyone else gets 403 */
  published: boolean
  /** public → anyone with the URL; private → только участники (project_user). V1 flip is one-way. */
  visibility: 'public' | 'private'
  /** the widget's main colour, «#rrggbb»; null = the service's green (shared/utils/widgetColor) */
  widget_color: string | null
  categories: Category[]
  statuses: Status[]
}

/** Short board descriptor returned in Me and in the hub list. */
export interface BoardSummary {
  id: number
  name: string
  slug: string
  /** The current user's role on this board — slice 1 of roles. Null for legacy records. */
  role?: ProjectRole | null
  /** Access requests waiting on this board; 0 where the user doesn't decide (only owner / admin do). */
  pending_requests?: number
  /** open or closed: the side menu lists the «Доступ» sections of this mode */
  visibility?: 'public' | 'private'
}

/**
 * Levels on a board. Owner is one per board and never assignable via UI; the rest
 * are chosen in participants / invitations screens. Keep in sync with
 * backend/app/Enums/ProjectRole.php.
 */
export const PROJECT_ROLES = ['owner', 'admin', 'manager', 'voter', 'reader'] as const
export type ProjectRole = (typeof PROJECT_ROLES)[number]

/** Roles the UI lets admins pick. Owner isn't here — see docs/ROLES_PLAN.md §4. */
export const ASSIGNABLE_ROLES: Exclude<ProjectRole, 'owner'>[] = ['admin', 'manager', 'voter', 'reader']

/** An open board has only a team: anyone by the link is a guest there (backend ProjectRole::openBoardAssignable). */
export const TEAM_ROLES: Exclude<ProjectRole, 'owner'>[] = ['admin', 'manager']

/** What each role may do — shown under the role in the role pickers. Mirrors docs/ROLES_PLAN.md §1. */
export const ROLE_HINTS: Record<ProjectRole, string> = {
  owner: 'Всё, включая удаление доски',
  admin: 'Всё, кроме удаления доски: настройки, категории, столбцы, участники',
  manager: 'Разбирает посты: статусы, ответы, скрытие',
  voter: 'Читает, голосует и предлагает',
  reader: 'Только читает',
}

/** Human-readable role names. */
export const ROLE_LABELS: Record<ProjectRole, string> = {
  owner: 'Владелец',
  admin: 'Админ',
  manager: 'Менеджер',
  voter: 'Участник',
  reader: 'Читатель',
}

/** A face on the team's card: one of the latest voters (backend VoterPreview); name is null for a nameless guest. */
export interface VoterPreview {
  name: string | null
  account: boolean
}

/** One row in the «кто голосовал» list (team-only). `account: true` = known user, `false` = anonymous widget vote. */
export interface Voter {
  name: string
  kind: VoteValue
  account: boolean
  created_at: string
}

export interface Me {
  id: number
  name: string
  email: string
  /** Soft mode: the account works either way, the cabinet shows a banner until the letter's link is opened. */
  email_verified: boolean
  /** Legacy projects via project_user pivot — drives team-mode login on the board. */
  projects: BoardSummary[]
  /** Self-serve boards owned by this user — drives the admin hub. */
  boards: BoardSummary[]
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  /** posts list with new_first=1: how many of the matching posts are still in «Новое» */
  new_count?: number
}

export interface Item<T> {
  data: T
}

/** Non-paginated list response ({"data": [...]}). */
export interface Collection<T> {
  data: T[]
}

export interface VoteState {
  likes_count: number
  dislikes_count: number
  my_vote: VoteValue | null
}
