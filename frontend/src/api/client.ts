import { voterToken } from '@/lib/voter'
import { TEXT } from '@/lib/dict'

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
  ) {
    super(message)
  }

  /** First validation message for a field, e.g. errors.title[0]. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

interface Options {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
  body?: unknown
  query?: Record<string, string | number | boolean | null | undefined>
  /** Service origin for cross-site calls (the widget). Omitted on the board: same origin, with session. */
  origin?: string
  /** Send X-Voter-Token. Off for the widget: a custom header would force a CORS preflight. */
  voter?: boolean
}

function cookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))
  return match?.[1] ? decodeURIComponent(match[1]) : null
}

// Sanctum SPA: every non-GET request from the board needs the XSRF cookie first.
let csrf: Promise<void> | null = null
function ensureCsrf(): Promise<void> {
  if (cookie('XSRF-TOKEN')) return Promise.resolve()
  csrf ??= fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).then(() => undefined)
  return csrf
}

export async function api<T>(path: string, opts: Options = {}): Promise<T> {
  const method = opts.method ?? 'GET'
  const sameOrigin = !opts.origin
  const url = new URL(`/api${path}`, opts.origin ?? location.origin)
  for (const [key, value] of Object.entries(opts.query ?? {})) {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
  }

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (opts.voter ?? sameOrigin) headers['X-Voter-Token'] = voterToken()

  let body: BodyInit | undefined
  if (opts.body instanceof FormData) body = opts.body
  else if (opts.body !== undefined) {
    body = JSON.stringify(opts.body)
    headers['Content-Type'] = 'application/json'
  }

  if (sameOrigin && method !== 'GET') {
    await ensureCsrf()
    const token = cookie('XSRF-TOKEN')
    if (token) headers['X-XSRF-TOKEN'] = token
  }

  let response: Response
  try {
    response = await fetch(url, { method, headers, body, credentials: sameOrigin ? 'same-origin' : 'omit' })
  } catch {
    throw new ApiError(0, TEXT.errors.network)
  }

  if (response.status === 204) return undefined as T
  const data = await response.json().catch(() => ({}))
  if (!response.ok) {
    if (response.status === 419) csrf = null
    const fallback = TEXT.errors[response.status as keyof typeof TEXT.errors] ?? TEXT.errors.generic
    throw new ApiError(response.status, response.status >= 500 ? fallback : (data.message ?? fallback), data.errors ?? {})
  }
  return data as T
}