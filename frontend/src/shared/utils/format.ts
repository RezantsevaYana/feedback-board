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

/** "только что" / "5 мин назад" / "вчера" / "15 окт" — compact relative time for lists. */
export function fmtTimeAgo(iso: string | null | undefined): string {
  if (!iso) return '—'
  const diffMs = Date.now() - new Date(iso).getTime()
  const sec = Math.floor(diffMs / 1000)
  if (sec < 60) return 'только что'
  const min = Math.floor(sec / 60)
  if (min < 60) return `${min} ${plural(min, 'минуту', 'минуты', 'минут')} назад`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr} ${plural(hr, 'час', 'часа', 'часов')} назад`
  const day = Math.floor(hr / 24)
  if (day === 1) return 'вчера'
  if (day < 7) return `${day} ${plural(day, 'день', 'дня', 'дней')} назад`
  return fmtDate(iso)
}