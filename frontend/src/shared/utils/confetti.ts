// Простой «салют» без зависимостей: 60 цветных кусочков падают сверху
// и вращаются CSS-анимацией. Контейнер удаляется сам через 3.5 сек.
// Срабатывает один раз на событие — вызываться должен тоже один раз.
const COLORS = ['#27ad5a', '#8179f4', '#ffd166', '#ef476f', '#06b6d4', '#f97316']

export function fireConfetti(): void {
  if (typeof document === 'undefined') return
  // Уважаем prefers-reduced-motion — пропускаем анимацию
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return

  const box = document.createElement('div')
  box.className = 'confetti'

  for (let i = 0; i < 60; i++) {
    const p = document.createElement('span')
    p.className = 'confetti-piece'
    p.style.setProperty('--x', `${Math.random() * 100}%`)
    p.style.setProperty('--c', COLORS[i % COLORS.length] ?? COLORS[0]!)
    p.style.setProperty('--d', `${(Math.random() * 0.5).toFixed(2)}s`)
    p.style.setProperty('--r', `${Math.round(Math.random() * 360)}deg`)
    p.style.setProperty('--s', `${(0.7 + Math.random() * 0.6).toFixed(2)}`)
    box.appendChild(p)
  }

  document.body.appendChild(box)
  setTimeout(() => box.remove(), 3500)
}
