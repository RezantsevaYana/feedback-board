// All interface texts in one place (TZ 8: ready for localization later).
import type { PostPriority, PostStatus } from '@/api/types'

export const STATUS: Record<PostStatus, string> = {
  new: 'Новое',
  done: 'Готово',
  rejected: 'Отклонено',
}
export const STATUSES: PostStatus[] = ['new', 'done', 'rejected']

/** What the team is asked when a card moves to «Готово» / «Отклонено». */
export const STATUS_REPLY: Partial<Record<PostStatus, { title: string; label: string; placeholder: string; required: boolean }>> = {
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
    required: true,
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