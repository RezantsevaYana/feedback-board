import { request } from './client'
import type { Item, Me } from './types'

const BASE_URL = '/admin'

export const AuthApi = {
  // the logged-in team member; 401 for guests
  me() {
    return request<Item<Me>>(`${BASE_URL}/me`)
  },

  // team login (Sanctum session)
  login(email: string, password: string) {
    return request<Item<Me>>(`${BASE_URL}/login`, { method: 'POST', body: { email, password } })
  },

  // self-serve registration — creates a user and logs in in the same request
  register(name: string, email: string, password: string) {
    return request<Item<Me>>(`${BASE_URL}/register`, { method: 'POST', body: { name, email, password } })
  },

  logout() {
    return request<void>(`${BASE_URL}/logout`, { method: 'POST' })
  },
}