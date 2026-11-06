// The board's settings in the cabinet, four sections with an address each (TEST.md п. 42,
// prototype frontend/demo/settings-rework-prototype.html, variant B «Разделы»). One list for the side menu,
// the header's board switcher (it keeps the section when switching boards) and the page heads.
export type BoardPage = 'settings' | 'access' | 'structure' | 'widget'

export const BOARD_PAGES: { page: BoardPage; route: string; label: string }[] = [
  { page: 'settings', route: 'board-settings', label: 'Общее' },
  { page: 'access', route: 'board-access', label: 'Доступ' },
  { page: 'structure', route: 'board-structure', label: 'Категории и статусы' },
  { page: 'widget', route: 'board-widget', label: 'Виджет' },
]

/** The section a route name belongs to, or undefined off the board's settings. */
export function boardPageOf(routeName: unknown): BoardPage | undefined {
  return BOARD_PAGES.find((p) => p.route === routeName)?.page
}
