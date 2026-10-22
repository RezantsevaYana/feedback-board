---
target: frontend/src/pages/board/components/BulkActionBar.vue
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 2
p1_count: 3
target_identity: "file:/Users/rezanceva96/PhpstormProjects/feedback-board/frontend/frontend/src/pages/board/components/BulkActionBar.vue"
timestamp: 2026-10-22T10-34-24Z
slug: pages-board-components-bulkactionbar-vue-4eb4079b
---
Method: dual-agent (A: af728310131b7439d · B: a4ded0f32b69590c0)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Zero-selection in-mode state has no bar, no caption |
| 2 | Match System / Real World | 2 | "Выделить" ambiguous; 3 verbs for exit (Готово / Снять выделение / ×) |
| 3 | User Control and Freedom | 3 | Esc cascade shaped well; no undo on bulk ops |
| 4 | Consistency and Standards | 3 | Pills reused inside popups is on-brand; exit split across 3 affordances |
| 5 | Error Prevention | 3 | Delete confirms with count; empty-reply pathway on bulk done/rejected broadcasts silently |
| 6 | Recognition Rather Than Recall | 2 | No icons on the bar; everything is text |
| 7 | Flexibility and Efficiency | 1 | No ⌘A, no shortcut to enter mode, no 1..9 for status; Shift-click undiscovered |
| 8 | Aesthetic and Minimalist Design | 3 | Lean bar; .list-top link appearing/disappearing causes jitter |
| 9 | Error Recovery | 2 | Bulk flows have no "Отменить"; single-post flow does |
| 10 | Help and Documentation | 3 | Operate-appropriate; author head-up is the warm nudge |
| **Total** | | **24/40** | **Acceptable** |

## Design Specificity Verdict

**LLM assessment.** Rocont-adjacent via tokens. The strongest brand gesture is real .pill and .type.slant tags inside the popups (`BulkActionBar.vue:25`, `:38`) — the dropdowns feel like the board itself. Sage selected tint + brand-green accent-color carry identity passively. But the chrome itself (rounded panel, 14px, chevrons, "Выбрано: N") is a template SaaS bulk toolbar; swap tokens and this is any admin tool. Rise+fade is correct restraint for Operate; no bar-specific detail makes it feel like Rocont.

**Deterministic scan.** `impeccable detect --json` on 3 target .vue files: exit 0, zero findings. Real issues live below detector floor (contrast on danger button in dark; menu keyboard a11y; store-watcher firing on search keystroke).

**Visual overlays.** Skipped — Vite dev ports held by Docker.

## Overall Impression

Engineering is tidy; discipline is right (Esc cascade, author head-up, ref<Set>+mutate, pill popups). Three structural flaws drag from "ships cleanly" to "ship after a pass":

1. No undo on bulk → biggest trust-breaker for an Operate tool.
2. Sub-menu keyboard a11y incomplete; project's own MenuComponent already solves this.
3. --bug danger button fails AA in dark: 4.08:1 for 14px.

Biggest opportunity: lift keyboard model to Operate standard — Cmd/Ctrl+A, A to enter mode, 1..9 to pick column, roving tabindex in menus.

## What's Working

- Shared .pill and .type tags inside the sub-menus (`BulkActionBar.vue:22-47`) — Rocont-specific gesture.
- Esc cascade at `BulkActionBar.vue:144-148` — menu → clear → exit.
- `ref<Set>` + `mutate()` reactivity (`useBoardStore.ts:262-271`) — self-aware comment, pattern works.
- Author head-up in bulk reply (`StatusReplyModal.vue:7-9`) — warm nudge toward concrete message.

## Priority Issues

### [P0] No undo on bulk move / categorize / hide

- **Why it matters.** Single-post wraps every triage toast with "Отменить" (`useTriage.ts:34-38`, `StatusReplyModal.vue:93-96`). Bulk does not (`useBoardStore.ts:333-364`). A mistaken Отклонено on 15 cards is unrecoverable without manual re-triage. For an Operate tool used daily, this is the biggest trust-breaker.
- **Fix.** Snapshot per-post {id, status.id, team_comment} (or category_id / is_hidden) before each bulk mutation; pass to `notification.success(text, { label: 'Отменить', run: () => reverse via bulk endpoint })`. Keep pre-confirm for bulkDelete (destructive = guard, not undo).
- **Suggested command.** `/impeccable harden`

### [P0] --bug danger button fails WCAG AA in dark theme

- **Why it matters.** `.btn.danger` text `color: var(--bug)` (`_bulk-action-bar.scss:75`). Dark --bug `#ec8a80` on --surface `#3a4442` = **4.08:1** for 14px normal. Passes AA Large only. "Удалить" is the single most consequential bar action; dark-mode admins work with sub-threshold contrast.
- **Fix.** Bump --bug on dark to ≥4.5:1 against --surface. Candidate: `#efb0a6` (lighter, 4.75:1). Or push button to filled: `background: color-mix(--bug 18%, transparent); color: #ffffff` on hover — matches status pill pattern.
- **Suggested command.** `/impeccable harden`

### [P1] Sub-menu keyboard navigation incomplete

- **Why it matters.** `BulkActionBar.vue:84-86` toggle() only flips a ref — no `nextTick → focus()` on first item. No @keydown on .bulk-menu or .bulk-item. `MenuComponent.vue:72-100` in same project implements ArrowDown/Up/Home/End/Esc/Tab. For Sam the menus are unreachable; for Alex it's mouse-round-trip friction. The project already solved this.
- **Fix.** Either wrap sub-menus in MenuComponent (pills via slot), or copy the open-at-index + arrow-key + focus-return pattern. Add @keydown on .bulk-menu, focus() first item on open, return focus to trigger on close.
- **Suggested command.** `/impeccable harden`

### [P1] Search keystroke exits selection mode entirely

- **Why it matters.** `useBoardStore.ts:378-380` watches `[view.section, view.layout, view.category, view.status, view.search, view.priority, view.showHidden]` and calls exitSelection() on any change. `view.search` fires every keystroke. Admin picks 10 posts, types "доставка" to narrow — selection mode killed on first letter, "Выделить" must be pressed again. Comment "начат в другом контексте" is wrong for search specifically.
- **Fix.** Split the watcher. section/layout/showHidden → exitSelection(). search/category/status/priority → clearSelection() only (keep mode). Debounce search would help but splitting is the root fix.
- **Suggested command.** `/impeccable harden`

### [P1] Zero-selection in-mode state is a dead screen

- **Why it matters.** On Выделить with 0 picks: 20 unchecked checkboxes + label flip to Готово. No bar, no caption. First-time admins stare trying to work out what state they're in. The user explicitly asked whether admins will realise this is a selection-mode entry — right now they infer, with a beat of "did it work?"
- **Fix.** Render the bar immediately on selectionMode=true with muted empty state "Выберите карточки — Shift+клик для диапазона, A — выделить всё". Introduces bar's existence before first click; advertises Shift-click + the shortcut keyboard P1 will add. Keep rise transition on first-mount only.
- **Suggested command.** `/impeccable onboard`

### [P2] "Выделить" verb ambiguity + three ways to exit

- **Why it matters.** "Выделить" reads as highlight ≠ select in Russian. In-mode: "Готово" (exits), "Снять выделение" (clears), bar "×" (exits). Three verbs for stop-doing-this, two synonymous-looking.
- **Fix.** Entry: "Выбрать карточки". In-mode top: single "Выйти из выбора" (replace Готово); "Снять выделение" stays as clear-only. Drop bar "×" — redundant with top-row exit + Esc. One exit affordance per location.
- **Suggested command.** `/impeccable clarify`

## Persona Red Flags

**Alex (power user).** Primary casualty. Shift-click is the only speed primitive, undiscovered. No select-all, no numeric status shortcuts, no undo. Will batch-select 20, mis-move 20, curse.

**Sam (a11y).** Native checkbox + :focus-visible — good. role=menu + aria-expanded on triggers — good. BUT: no keyboard open-first-item, no arrow-key traversal, no focus-return on close. Author head-up <p class="note"> is unlabeled — SR announces as plain text, not tied to textarea.

**Riley (stress tester).** StatusReplyModal z-index: 20 opens behind BulkActionBar z-index: 50 (`_modal.scss:7` vs `_bulk-action-bar.scss:19`). BaseModal has its own overlay so visually ok, but layering is inverted. Bar at bottom: 20px overlaps "Показать ещё" on short viewport. Toast z-index: 60 > bar — admin may miss next-batch-ready state.

## Minor Observations

- `StatusReplyModal.vue:68-71` posts.length used instead of unique-author count — "увидят 2 авторов: Аня" when two posts share one author.
- .bulk-trigger has no chevron rotation on open — AppIcon stays static.
- BulkActionBar + PostList both mount <StatusReplyModal> conditionally — two instances can race.
- enterSelection() dead-export mismatch at useBoardStore.ts:273-275.
- .bulk-menu max-height 60vh + bottom: calc(100% + 8px) — on short laptop pushes menu off-screen. No viewport collision logic.
- .card-check mobile 18×18 absolute — below 44×44 iOS tap floor.
- bulkHide success count may lie when selection mixes hidden + visible.
- Danger confirm copy at BulkActionBar.vue:117 is the only line with brand voice.

## Questions to Consider

- If the primary job is "clear 20 posts in Новое fast," why does entering mode not auto-select the whole visible column, inverting work from pick-to-act to deselect-the-few?
- Is Скрыть/Показать as context-aware toggle a kindness or a trap? Mixed selection silently no-ops.
- Brand says "warm tool with humour." Nothing on the bar is warm. Should it carry one line of personality — "Разобрано: 7" instead of "Выбрано: 7" — or does Operate forbid it?
