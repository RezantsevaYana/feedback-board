const dateFormat = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short' })

export function fmtDate(iso: string | null | undefined): string {
  return iso ? dateFormat.format(new Date(iso)) : '—'
}

/** Russian plural: plural(5, 'голос', 'голоса', 'голосов') → 'голосов'. */
export function plural(n: number, one: string, few: string, many: string): string {
  const m10 = n % 10
  const m100 = n % 100
  if (m10 === 1 && m100 !== 11) return one
  if (m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20)) return few
  return many
}