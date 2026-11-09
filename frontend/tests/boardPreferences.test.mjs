import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readBoardPreferences, saveBoardPreferences } from '../src/shared/utils/boardPreferences.ts'

const defaults = () => ({
  layout: 'kanban', category: [], status: [], search: '', sort: 'manual', priority: [], showHidden: true,
})
const available = { categories: [1, 2], statuses: [3, 4], team: true }

function storage() {
  const values = new Map()
  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: {
      getItem: key => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    },
  })
  return values
}

test('restores filters, search, sorting and layout after saving', () => {
  storage()
  const selected = { layout: 'list', category: [1, 'none'], status: [4], search: 'Канбан', sort: 'priority', priority: ['high', 'none'], showHidden: false }
  saveBoardPreferences('board-a:user-1', selected)
  assert.deepEqual(readBoardPreferences('board-a:user-1', defaults(), available), selected)
})

test('another board or account starts with its own defaults', () => {
  storage()
  saveBoardPreferences('board-a:user-1', { ...defaults(), layout: 'list', category: [1] })
  assert.deepEqual(readBoardPreferences('board-b:user-1', defaults(), available), defaults())
  assert.deepEqual(readBoardPreferences('board-a:user-2', defaults(), available), defaults())
})

test('ignores deleted filters, duplicate ids and unsupported saved values', () => {
  const values = storage()
  values.set('board', JSON.stringify({ layout: 'invalid', category: [1, 1, 99, '1'], status: [3, 99, 3], sort: 'invalid', priority: ['high', 'invalid'], search: ' idea ', showHidden: 'false' }))
  assert.deepEqual(readBoardPreferences('board', defaults(), available), {
    ...defaults(), category: [1], status: [3], priority: ['high'], search: 'idea',
  })
})

test('does not restore team filters when team rights are lost', () => {
  storage()
  saveBoardPreferences('board', { ...defaults(), sort: 'priority', priority: ['critical'], showHidden: false })
  const visitorDefaults = { ...defaults(), sort: 'new' }
  assert.deepEqual(readBoardPreferences('board', visitorDefaults, { ...available, team: false }), visitorDefaults)
})

test('reset replaces saved filters while keeping the selected layout', () => {
  storage()
  saveBoardPreferences('board', { ...defaults(), layout: 'list', category: [1], search: 'idea' })
  saveBoardPreferences('board', { ...defaults(), layout: 'list' })
  assert.deepEqual(readBoardPreferences('board', defaults(), available), { ...defaults(), layout: 'list' })
})

test('corrupt or unavailable storage never blocks opening the board', () => {
  const values = storage()
  for (const invalid of ['{', 'null', '[]', '42']) {
    values.set('board', invalid)
    assert.deepEqual(readBoardPreferences('board', defaults(), available), defaults())
  }
  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    get() { throw new Error('Storage is blocked') },
  })
  assert.deepEqual(readBoardPreferences('board', defaults(), available), defaults())
  assert.doesNotThrow(() => saveBoardPreferences('board', defaults()))
})
