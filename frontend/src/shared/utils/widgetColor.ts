// The widget's main colour: one HEX from the owner becomes the handful of tokens the widget paints with.
// Used by the widget itself and by the preview on the «Доступ» page, so both show the same thing.

/** The service's own green (--accent in base/_tokens.scss): what the widget uses when no colour is set. */
export const DEFAULT_WIDGET_COLOR = '#1d8547'

const INK = '#0f2224' // --fg: dark text on a light button, and the colour links are darkened towards
const WHITE = '#ffffff'

/** «1D8547», «#1d8547», «#1d8» → «#1d8547»; anything that isn't a HEX colour → null. Mirrors the API's normalisation. */
export function normalizeHex(input: string): string | null {
  let hex = input.trim().replace(/^#/, '').toLowerCase()
  if (/^[0-9a-f]{3}$/.test(hex)) hex = hex.replace(/./g, (c) => c + c)
  return /^[0-9a-f]{6}$/.test(hex) ? `#${hex}` : null
}

function rgb(hex: string): [number, number, number] {
  const n = parseInt(hex.slice(1), 16)
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

function toHex([r, g, b]: [number, number, number]): string {
  return `#${[r, g, b].map((c) => Math.round(c).toString(16).padStart(2, '0')).join('')}`
}

/** a over b: weight 0 → b, weight 1 → a */
function mix(a: string, b: string, weight: number): string {
  const [ra, ga, ba] = rgb(a)
  const [rb, gb, bb] = rgb(b)
  return toHex([ra * weight + rb * (1 - weight), ga * weight + gb * (1 - weight), ba * weight + bb * (1 - weight)])
}

function luminance(hex: string): number {
  const [r, g, b] = rgb(hex).map((c) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r! + 0.7152 * g! + 0.0722 * b!
}

/** WCAG contrast ratio, 1…21 */
export function contrast(a: string, b: string): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
  return (hi! + 0.05) / (lo! + 0.05)
}

/** Steps the colour towards ink until it reads on the background (4.5:1, normal text). */
function readableOn(color: string, background: string): string {
  let out = color
  for (let w = 0.9; contrast(out, background) < 4.5 && w > 0; w -= 0.1) out = mix(color, INK, w)
  return out
}

/** Text on the filled button: white while it holds 4.5:1, otherwise the dark ink (yellow, mint, light pink…). */
export function textOn(color: string): string {
  return contrast(WHITE, color) >= 4.5 ? WHITE : INK
}

/**
 * CSS custom properties for the widget's root: the same names as base/_tokens.scss, so every rule
 * (buttons, chips, focus rings, links) follows the chosen colour without widget-only overrides.
 */
export function widgetPalette(color: string | null | undefined): Record<string, string> {
  const accent = (color && normalizeHex(color)) || DEFAULT_WIDGET_COLOR
  const ink = readableOn(accent, WHITE)
  const active = mix(accent, WHITE, 0.2)
  return {
    '--accent': accent,
    '--accent-fg': textOn(accent),
    '--accent-ink': ink,
    '--focus': ink,
    '--active': active,
    '--active-fg': readableOn(mix(accent, INK, 0.45), active),
    '--active-line': ink,
  }
}