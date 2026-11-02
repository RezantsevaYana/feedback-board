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

  // the link from the letter: path and signed query go to the API as they are; no session needed, 403 if stale
  verifyEmail(id: string, hash: string, expires: string, signature: string) {
    return request<Item<{ id: number; email: string }>>(`${BASE_URL}/email/verify/${id}/${hash}`, {
      method: 'POST',
      query: { expires, signature },
    })
  },

  // forgotten password: the letter with a link; 204 whether the address is known or not
  forgotPassword(email: string) {
    return request<void>(`${BASE_URL}/forgot-password`, { method: 'POST', body: { email } })
  },

  // new password from the letter's link; logs in like login(), 422 on a stale or used token
  resetPassword(token: string, email: string, password: string) {
    return request<Item<Me>>(`${BASE_URL}/reset-password`, { method: 'POST', body: { token, email, password } })
  },

  // the reset page on open: 204 if the link still works, 422 (token) if it's used or stale
  checkResetToken(token: string, email: string) {
    return request<void>(`${BASE_URL}/reset-password/check`, { method: 'POST', body: { token, email } })
  },

  // «Настройки аккаунта» → «Профиль»: the name; returns /me with it
  updateProfile(name: string) {
    return request<Item<Me>>(`${BASE_URL}/me`, { method: 'PATCH', body: { name } })
  },

  // «Сменить пароль» in the account settings; 422 on a wrong current password, then a «Пароль изменён» letter
  changePassword(currentPassword: string, password: string) {
    return request<void>(`${BASE_URL}/me/password`, {
      method: 'PATCH',
      body: { current_password: currentPassword, password },
    })
  },

  // send the confirmation letter again (6 per 10 minutes)
  resendVerification() {
    return request<void>(`${BASE_URL}/email/verification-notification`, { method: 'POST' })
  },
}