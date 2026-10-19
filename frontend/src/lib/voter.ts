// Anonymous voter id for this browser (TZ 5.5): generated once, kept in localStorage.
const KEY = 'fb-voter-token'

let memoryToken: string | null = null

function uuid(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID()
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}

export function voterToken(): string {
  try {
    let token = localStorage.getItem(KEY)
    if (!token) {
      token = uuid()
      localStorage.setItem(KEY, token)
    }
    return token
  } catch {
    // private mode or blocked storage: one token per page load
    memoryToken ??= uuid()
    return memoryToken
  }
}