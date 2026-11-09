import { request } from './client'
import type { Item } from './types'

export type LegalDocumentKey = 'privacy' | 'terms' | 'personal_data'
export type LegalAcceptanceKey = Exclude<LegalDocumentKey, 'privacy'>
export interface LegalDocumentSnapshot {
  key: LegalDocumentKey
  title: string
  path: string
  version: string
  draft: boolean
  operator: { name: string | null; address: string | null; contact_email: string | null }
  sections: Array<{ title: string; text: string }>
}
export interface LegalDocument extends LegalDocumentSnapshot { content_hash: string }
export type LegalCatalog = Record<LegalDocumentKey, LegalDocument>
export type LegalPayload = Record<LegalAcceptanceKey, { accepted: boolean; version: string; content_hash: string }>
export const LegalApi = {
  catalog: () => request<Item<LegalCatalog>>('/public/legal-documents'),
}

export function legalErrors(payload: LegalPayload | null): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!payload) return { legal: 'Дождитесь загрузки документов или попробуйте загрузить их ещё раз.' }
  if (!payload.terms.accepted) errors['legal.terms.accepted'] = 'Подтвердите принятие пользовательского соглашения.'
  if (!payload.personal_data.accepted) errors['legal.personal_data.accepted'] = 'Подтвердите согласие на обработку персональных данных.'
  return errors
}
