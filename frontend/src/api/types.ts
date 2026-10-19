// Shapes of the Laravel API (backend/app/Http/Resources).

export type PostStatus = 'new' | 'done' | 'rejected'
export type PostPriority = 'low' | 'medium' | 'high' | 'critical'
export type VoteValue = 'like' | 'dislike'

/** Configured by the team per project («Баг», «Идея», «Доставка»…). */
export interface Category {
  id: number
  name: string
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
  status: PostStatus
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