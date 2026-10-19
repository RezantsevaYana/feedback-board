import { request } from './client'
import type { Item, Project } from './types'

const BASE_URL = '/public/projects'

export const ProjectsApi = {
  // project with its categories; `origin` when called from the widget on someone else's site
  get(slug: string, origin?: string) {
    return request<Item<Project>>(`${BASE_URL}/${encodeURIComponent(slug)}`, { origin })
  },
}