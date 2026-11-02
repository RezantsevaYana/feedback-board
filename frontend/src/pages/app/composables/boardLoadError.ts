import { ApiError, errorMessage } from '@/api/client'
import { NO_CONFIGURE_TEXT } from './useLostAccess'

/** Why a board's settings or embed screen didn't load, said in terms of what the owner can do next. */
export function boardLoadError(e: unknown): string {
  if (e instanceof ApiError && e.status === 404) {
    return 'Доски с таким адресом нет. Если её удалили, она лежит в корзине в списке досок, оттуда её можно восстановить.'
  }
  if (e instanceof ApiError && e.status === 403) return NO_CONFIGURE_TEXT
  return errorMessage(e)
}