---
target: board screen
total_score: 26
p0_count: 0
p1_count: 4
timestamp: 2026-10-21T13-19-29Z
slug: frontend-src-pages-board-boardpage-vue
---
# Critique: board screen (BoardPage.vue), 2026-10-21

Score 26/40 (Acceptable). Heuristics: status 3, real-world 3, control 2, consistency 3, error prevention 3, recognition 3, flexibility 1, minimal 3, recovery 3, help 2.

Slop verdict: passes. Bans hit: side stripe `.kcard-reply` border-left 2px (_kanban.scss:100); bounce easing on vote-hop (_votes.scss:62). Detector: 1 CLI finding (bounce-easing), browser: skipped-heading h1->h4, line-length on team hint; rest false positives (Vue DevTools, lightgallery CSS, Chrome extension glow).

Priority issues:
1. [P1] Mobile: filters bury the board (first card at y~780/844 in team mode, ~530 guest). Fix: «Фильтры · N» sheet, overflow menu for team box/theme. /impeccable adapt
2. [P1] Team toolbar ~11 controls on two rows, sort detached, «Скрытые» ambiguous. Fix: group view · filters · sort-as-select. /impeccable distill
3. [P1] Contrast below AA: column status pills on tray 3.9-4.3:1; hidden cards opacity .55 -> 2.5:1. Fix: darker --s-*, mark hidden without opacity. /impeccable audit
4. [P1] Triage mouse-only: no shortcuts, no undo, role=link card with nested buttons. Fix: card quick menu, J/K/1-3, «Отменить» in toast. /impeccable harden
5. [P2] Empty states: search miss shows «Пока пусто» x3; no reset; no win state for cleared «Новое». /impeccable onboard + delight

Minor: em dashes in copy; «—» as no-priority label; orphan theme toggle; mono for names in team modal; H1 duplicates nav; faint card edge in list view; ink toast; copy-link fails silently on http.
