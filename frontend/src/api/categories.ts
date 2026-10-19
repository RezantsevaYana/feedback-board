import { request } from './client'
import type { Category, Item } from './types'

const BASE_URL = '/admin'

export const CategoriesApi = {
  // add a category to the project
  create(slug: string, name: string) {
    return request<Item<Category>>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/categories`, { method: 'POST', body: { name } })
  },

  // rename a category
  rename(id: number, name: string) {
    return request<Item<Category>>(`${BASE_URL}/categories/${id}`, { method: 'PATCH', body: { name } })
  },

  // delete a category; its posts stay without one
  remove(id: number) {
    return request<void>(`${BASE_URL}/categories/${id}`, { method: 'DELETE' })
  },
}