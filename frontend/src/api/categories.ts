import { request } from './client'
import type { Category, PaletteColor, Item } from './types'

const BASE_URL = '/admin'

export const CategoriesApi = {
  // add a category to the project; null colour leaves the tag on the neutral fallback
  create(slug: string, name: string, color: PaletteColor | null) {
    return request<Item<Category>>(`${BASE_URL}/projects/${encodeURIComponent(slug)}/categories`, { method: 'POST', body: { name, color } })
  },

  // rename a category or change its colour; null clears the colour back to neutral
  update(id: number, name: string, color: PaletteColor | null) {
    return request<Item<Category>>(`${BASE_URL}/categories/${id}`, { method: 'PATCH', body: { name, color } })
  },

  // delete a category; its posts stay without one
  remove(id: number) {
    return request<void>(`${BASE_URL}/categories/${id}`, { method: 'DELETE' })
  },
}