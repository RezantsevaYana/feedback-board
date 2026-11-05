import type { PaletteColor } from '@/api/types'
import { normalizeHex, textOn } from './widgetColor'

// Existing named colours retain their theme tokens; custom colours use a contrasting foreground.
export function entityColorStyle(color: PaletteColor | null | undefined, variant: 'tag' | 'accent' = 'tag'): Record<string, string> {
  if (!color?.startsWith('#')) return {}
  const hex = normalizeHex(color)
  if (!hex) return {}
  if (variant === 'accent') return { '--sta': `color-mix(in srgb, ${hex} 30%, var(--fg))` }
  return { backgroundColor: hex, color: textOn(hex) }
}

export function entityColorHex(color: PaletteColor | null, variant: 'category' | 'status'): string {
  if (!color) return ''
  if (color.startsWith('#')) return normalizeHex(color) ?? ''
  const colors = variant === 'category'
    ? { red: '#f3cfc8', orange: '#f6d8b3', yellow: '#efe09f', green: '#cce4d1', blue: '#c9dde6', pink: '#efccd4' }
    : { red: '#8f3029', orange: '#7f4718', yellow: '#6b531a', green: '#14623a', blue: '#2a4e6a', pink: '#8a3059' }
  return colors[color as keyof typeof colors]
}
