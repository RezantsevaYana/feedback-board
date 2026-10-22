import type { Category, Post } from './types'
import { request } from './client'

const BASE_URL = '/public'

export interface WeeklyWinnerEntry {
  category: Pick<Category, 'id' | 'name' | 'color'> & { position: number }
  post: Post | null
}

export interface WeeklyWinnersMeta {
  week_start: string
  week_end: string
}

interface WeeklyWinnersResponse {
  data: WeeklyWinnerEntry[]
  meta: WeeklyWinnersMeta
}

export const WeeklyWinnersApi = {
  // один победитель на категорию за текущую ISO-неделю
  get(slug: string) {
    return request<WeeklyWinnersResponse>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/weekly-winners`)
  },
}
