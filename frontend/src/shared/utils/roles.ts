import type { ProjectRole } from '@/api/types'

// What a role may do on a board — mirrors backend/app/Enums/ProjectRole.php (the API checks it again).

/** Team mode: move cards, change statuses, hide posts, reply. */
export function canTriage(role: ProjectRole | null | undefined): boolean {
  return role === 'owner' || role === 'admin' || role === 'manager'
}

/** Categories, columns, board settings and participants. */
export function canConfigure(role: ProjectRole | null | undefined): boolean {
  return role === 'owner' || role === 'admin'
}