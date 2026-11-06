import { onBeforeUnmount } from 'vue'

// The session cookie is shared by every tab, the Pinia stores are not: after a login / logout in one tab the
// others kept the previous user's role, boards and posts until a reload. The tab that changed the session
// tells the others over BroadcastChannel; they ask to reload (TEST.md п. 25, AuthChangedModal in App.vue).
// No BroadcastChannel (old browsers, some private modes) → nothing is sent or heard, and the tab falls back
// to useRoleSync (/me again on focus and after a 403).

export interface AuthChanged {
  type: 'auth-changed'
  /** the user the session belongs to now; null after a logout */
  userId: number | null
  /** the tab that sent it, so a tab never reacts to its own message */
  from: string
}

const TAB_ID = Math.random().toString(36).slice(2)
const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('fb:auth') : null

/** Tell the other tabs the session now belongs to `userId` (null — logged out). */
export function broadcastAuthChange(userId: number | null): void {
  channel?.postMessage({ type: 'auth-changed', userId, from: TAB_ID } satisfies AuthChanged)
}

/** Listen to session changes made in other tabs while the calling component is mounted. */
export function useAuthBroadcast(onChange: (message: AuthChanged) => void): void {
  if (!channel) return
  const listener = (event: MessageEvent<AuthChanged>) => {
    if (event.data?.type === 'auth-changed' && event.data.from !== TAB_ID) onChange(event.data)
  }
  channel.addEventListener('message', listener)
  onBeforeUnmount(() => channel.removeEventListener('message', listener))
}
