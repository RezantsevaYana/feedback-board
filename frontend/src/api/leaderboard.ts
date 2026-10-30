import { request } from './client'

const BASE_URL = '/public'

export type LeaderboardScope = 'all' | 'week'
export type LeaderboardMetric = 'posts' | 'support'

export interface LeaderboardEntry {
  place: number
  /** Stable id of the author: `u:{id}` for an account, an opaque `t:…` for a guest browser (the token itself never comes back). */
  key: string
  name: string
  value: number
  /** The current visitor, by account or by this browser's token. */
  me: boolean
}

interface LeaderboardResponse {
  data: LeaderboardEntry[]
  meta: { scope: LeaderboardScope; metric: LeaderboardMetric }
}

export const LeaderboardApi = {
  // топ авторов проекта
  get(slug: string, query: { scope: LeaderboardScope; metric: LeaderboardMetric }) {
    return request<LeaderboardResponse>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/leaderboard`, { query })
  },
}
