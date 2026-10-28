import { ApiError, errorMessage } from '@/api/client'

/** Why a board's settings or embed screen didn't load, said in terms of what the owner can do next. */
export function boardLoadError(e: unknown): string {
  if (e instanceof ApiError && e.status === 404) {
    return 'Доски с таким адресом нет. Если её удалили, она лежит в корзине в списке досок, оттуда её можно восстановить.'
  }
  if (e instanceof ApiError && e.status === 403) return 'Настройки этой доски доступны только владельцу и админам.'
  return errorMessage(e)
}