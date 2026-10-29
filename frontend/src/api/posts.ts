import { request, type RequestOptions } from './client'
import type { Item, Paginated, Post, VoteState, VoteValue, Voter } from './types'

/** Ответ bulk-эндпоинтов: массив обновлённых карточек (кроме delete — он возвращает 204). */
type BulkResponse = { data: Post[] }

const PUBLIC_URL = '/public'
const ADMIN_URL = '/admin'

const projectPath = (slug: string) => `projects/${encodeURIComponent(slug)}/posts`

export type PostsQuery = RequestOptions['query']

/** Fields the team can change on a post. */
export type PostPatch = Partial<Pick<Post, 'title' | 'description' | 'priority' | 'team_comment' | 'is_hidden'>> & {
  category_id?: number | null
  status_id?: number
}

export const PostsApi = {
  // posts on the public board
  list(slug: string, query: PostsQuery) {
    return request<Paginated<Post>>(`${PUBLIC_URL}/${projectPath(slug)}`, { query })
  },

  // posts in team mode: hidden ones and priorities included
  adminList(slug: string, query: PostsQuery) {
    return request<Paginated<Post>>(`${ADMIN_URL}/${projectPath(slug)}`, { query })
  },

  // one post on the public board
  get(slug: string, id: number) {
    return request<Item<Post>>(`${PUBLIC_URL}/${projectPath(slug)}/${id}`)
  },

  // one post in team mode
  adminGet(id: number) {
    return request<Item<Post>>(`${ADMIN_URL}/posts/${id}`)
  },

  // who voted — triage team only; split into likes/dislikes by `kind` on the client
  voters(id: number) {
    return request<{ data: Voter[] }>(`${ADMIN_URL}/posts/${id}/voters`)
  },

  // new post from the form (multipart: screenshots); `origin` when sent from the widget
  create(slug: string, data: FormData, origin?: string) {
    return request<Item<Post>>(`${PUBLIC_URL}/${projectPath(slug)}`, { method: 'POST', body: data, origin })
  },

  // team edits a post
  update(id: number, data: PostPatch) {
    return request<Item<Post>>(`${ADMIN_URL}/posts/${id}`, { method: 'PATCH', body: data })
  },

  // team deletes a post with its screenshots
  remove(id: number) {
    return request<void>(`${ADMIN_URL}/posts/${id}`, { method: 'DELETE' })
  },

  // like / dislike from this browser
  vote(id: number, value: VoteValue) {
    return request<Item<VoteState>>(`${PUBLIC_URL}/posts/${id}/vote`, { method: 'POST', body: { value } })
  },

  // cancel this browser's vote
  unvote(id: number) {
    return request<Item<VoteState>>(`${PUBLIC_URL}/posts/${id}/vote`, { method: 'DELETE' })
  },

  // move a batch of posts to one column; `teamComment` ships to all when target is done/rejected
  bulkMove(ids: number[], statusId: number, teamComment: string | null = null) {
    return request<BulkResponse>(`${ADMIN_URL}/posts/bulk/move`, { method: 'POST', body: { ids, status_id: statusId, team_comment: teamComment } })
  },

  // set the same category on a batch (null clears the category back to «без категории»)
  bulkCategorize(ids: number[], categoryId: number | null) {
    return request<BulkResponse>(`${ADMIN_URL}/posts/bulk/categorize`, { method: 'POST', body: { ids, category_id: categoryId } })
  },

  // hide a batch from the public board or restore them
  bulkHide(ids: number[], hidden: boolean) {
    return request<BulkResponse>(`${ADMIN_URL}/posts/bulk/hide`, { method: 'POST', body: { ids, hidden } })
  },

  // delete a batch together with their screenshots
  bulkDelete(ids: number[]) {
    return request<void>(`${ADMIN_URL}/posts/bulk`, { method: 'DELETE', body: { ids } })
  },
}