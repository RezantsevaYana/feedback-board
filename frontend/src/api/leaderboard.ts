import { request } from './client'

const BASE_URL = '/public'

export type LeaderboardScope = 'all' | 'week'
export type LeaderboardMetric = 'posts' | 'support'

export interface LeaderboardEntry {
  place: number
  name: string
  value: number
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
