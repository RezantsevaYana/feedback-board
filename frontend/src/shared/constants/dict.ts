// All interface texts in one place (TZ 8: ready for localization later).
import type { PostPriority, SystemStatusSlug } from '@/api/types'

/**
 * Что спрашивает доска при переносе карточки в системный столбец.
 * Привязано к slug — для кастомных столбцов (В работе / Ревью) ничего не спрашиваем.
 */
export const STATUS_REPLY: Partial<Record<SystemStatusSlug, { title: string; label: string; placeholder: string; required: boolean }>> = {
  done: {
    title: 'Готово',
    label: 'Что именно сделано',
    placeholder: 'Например: добавили тёмную тему в настройках профиля, версия 3.14',
    required: false,
  },
  rejected: {
    title: 'Отклонить',
    label: 'Почему отклонено',
    placeholder: 'Например: это уже есть — наведите на сообщение и нажмите на смайлик',
    required: false,
  },
}

export const NO_CATEGORY = 'Без категории'

export const PRIORITY: Record<PostPriority, string> = {
  low: 'Низкий',
  medium: 'Средний',
  high: 'Высокий',
  critical: 'Критический',
}
export const PRIORITIES: PostPriority[] = ['critical', 'high', 'medium', 'low']

export const TEXT = {
  anonymous: 'Аноним',
  errors: {
    network: 'Нет связи с сервером. Проверьте интернет и попробуйте ещё раз.',
    generic: 'Что-то пошло не так. Попробуйте ещё раз.',
    404: 'Не найдено.',
    419: 'Сессия устарела. Обновите страницу.',
    429: 'Слишком много запросов. Подождите немного.',
  },
}
