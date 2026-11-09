export interface BoardPreferences {
  layout: 'kanban' | 'list'
  category: (number | 'none')[]
  status: number[]
  search: string
  sort: 'new' | 'top' | 'manual' | 'priority'
  priority: ('critical' | 'high' | 'medium' | 'low' | 'none')[]
  showHidden: boolean
}

export function readBoardPreferences(
  key: string,
  defaults: BoardPreferences,
  available: { categories: number[]; statuses: number[]; team: boolean },
): BoardPreferences {
  try {
    const saved: unknown = JSON.parse(localStorage.getItem(key) ?? 'null')
    if (!saved || typeof saved !== 'object' || Array.isArray(saved)) return defaults
    const data = saved as Record<string, unknown>
    const priorities = ['critical', 'high', 'medium', 'low', 'none']
    const sorts = available.team ? ['new', 'top', 'manual', 'priority'] : ['new', 'top']
    return {
      layout: data.layout === 'kanban' || data.layout === 'list' ? data.layout : defaults.layout,
      category: Array.isArray(data.category)
        ? [...new Set(data.category.filter((id): id is number | 'none' => id === 'none' || (typeof id === 'number' && available.categories.includes(id))))]
        : defaults.category,
      status: Array.isArray(data.status)
        ? [...new Set(data.status.filter((id): id is number => typeof id === 'number' && available.statuses.includes(id)))]
        : defaults.status,
      search: typeof data.search === 'string' ? data.search.trim().slice(0, 120) : defaults.search,
      sort: typeof data.sort === 'string' && sorts.includes(data.sort) ? data.sort as BoardPreferences['sort'] : defaults.sort,
      priority: available.team && Array.isArray(data.priority)
        ? [...new Set(data.priority.filter((value): value is BoardPreferences['priority'][number] => typeof value === 'string' && priorities.includes(value)))]
        : defaults.priority,
      showHidden: available.team && typeof data.showHidden === 'boolean' ? data.showHidden : defaults.showHidden,
    }
  } catch {
    return defaults
  }
}

export function saveBoardPreferences(key: string, preferences: BoardPreferences): void {
  try {
    localStorage.setItem(key, JSON.stringify(preferences))
  } catch {
    // A full or unavailable browser storage must not prevent using the board.
  }
}
