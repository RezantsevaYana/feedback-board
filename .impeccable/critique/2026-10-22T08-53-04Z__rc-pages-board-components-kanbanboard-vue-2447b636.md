---
target: frontend/src/pages/board/components/KanbanBoard.vue
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 2
p1_count: 2
target_identity: "file:/Users/rezanceva96/PhpstormProjects/feedback-board/frontend/src/pages/board/components/KanbanBoard.vue"
target_fingerprint: "sha256:357fa2e28bb5dcb65fb6ab853dd8aaa5cd6cb47b27b7d28966a2a7c3783315e5"
target_path: /Users/rezanceva96/PhpstormProjects/feedback-board/frontend/src/pages/board/components/KanbanBoard.vue
timestamp: 2026-10-22T08-53-04Z
slug: rc-pages-board-components-kanbanboard-vue-2447b636
---
Method: dual-agent (A: a55d7378cae06c87c · B: a85f77d30c494ec45)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Selected swatch has a green ring, but neither modal shows a live preview of the resulting pill/tag as the admin picks |
| 2 | Match System / Real World | 3 | Pastel `red` vs pastel `pink` differ by ~2 points of hue (`#f3cfc8` vs `#efccd4`); same for `orange` vs `yellow` cream |
| 3 | User Control and Freedom | 2 | `ColorPalette.vue:20` uses `defineModel<PaletteColor>({ required: true })`; a row that was `color: null` gets forcibly recolored to red on edit |
| 4 | Consistency and Standards | 2 | Picker pastel (`--cat-*`) vs status pill saturated-on-tint (`--sta-*`). Same slug `red` → `#f3cfc8` in picker, `#8f3029` on the board |
| 5 | Error Prevention | 3 | Default new row = `PALETTE_COLORS[0]` ('red'); meaning collision with `rejected` |
| 6 | Recognition Rather Than Recall | 2 | `aria-label="Цвет категории"` is hard-coded in `ColorPalette.vue:2` and reused unchanged in `StatusesModal` |
| 7 | Flexibility and Efficiency | 2 | `role="radiogroup"` without roving tabindex and no Arrow-key handler; every swatch is a tab stop |
| 8 | Aesthetic and Minimalist Design | 3 | Picker clean, but 6 hues × many places erodes "one hue" promise |
| 9 | Error Recovery | 3 | `ApiError.field('color')` is surfaced in both modals |
| 10 | Help and Documentation | 2 | Modal `.note` explains ordering but says nothing about what color means |
| **Total** | | **25/40** | **Acceptable** |

## Design Specificity Verdict

**LLM assessment.** Partially authored for the product, partially fighting its own manifesto. On-brand: pill treatment (text + 12% tint via `color-mix` at `_tags.scss:16-25`), slug symmetry between `--cat-*` and `--sta-*`, use of `--active-line` as the selected-swatch ring. Off-brand: `_tokens.scss:2-4` promises "green is the one hue, purple left out" — twelve lines later, six category hues + six status hues. The pastel treatment is the only thing keeping it off the Trello line, and on a real board (3 default statuses + custom + categories) the "one hue" promise visibly cracks.

**Deterministic scan.** `impeccable detect --json` across 8 target `.vue` files: exit 0, zero findings. The detector reads markup/attributes; real issues here live in CSS tokens, contrast math, and a11y wiring.

**Visual overlays.** Skipped — no live Vite surface detected (ports 5173/5174 bound by Docker). Review was source + hard contrast math.

## Overall Impression

Engineering is tidy. The system is wired coherently end-to-end: slugs flow DB → models → resources → store → tokens → CSS; no raw hex in Vue files; slug coverage in `_tags.scss` is 6-of-6 for both `.pill` and `.type`.

Three structural flaws drag it from "polish" to "needs a pass":
1. Picker lies about output (pastel swatch, saturated pill).
2. Two status colors fail WCAG AA on 12px text in light mode, and the token comment claims the opposite.
3. The picker's accessibility contract is half-written.

Biggest opportunity: shrink the status palette to three semantic positions (go / neutral / stop) and leave 6 colors for categories.

## What's Working

- Pill treatment at `_tags.scss:16-25` (text-color + 12% tint via `color-mix`) keeps the board quiet and avoids Trello chips. In dark theme all six pass AA by 5.73–8.46:1.
- Slug symmetry: `PALETTE_COLORS` is single source of truth, mirrored into `--cat-*` and `--sta-*`, type-safe end-to-end; raw-hex audit across the 8 target `.vue` files is empty.
- Selected-ring at `_categories-modal.scss:90-93` uses `var(--active-line)` — ties the picker's current-choice visual to the brand green that already carries that meaning.

## Priority Issues

### [P0] Light-theme `--sta-orange` and `--sta-yellow` fail WCAG AA for 12px pill text

- **Why it matters.** `_tokens.scss:35` promises "each at 5.2:1 or more". On `--surface-2` plain: orange **4.09**, yellow **4.25**. On the real pill bg (12% tint over `--surface-2`): orange **3.54**, yellow **3.68**. 12px is not WCAG "large text". Both fail AA.
- **Fix.** Darken the tokens so they clear 4.5:1 on the tinted bg (e.g. `--sta-orange: #7f4718`, `--sta-yellow: #6b531a`), or raise the tint from 12% to ~18%. Correct or delete the misleading token comment.
- **Suggested command.** `/impeccable harden`

### [P0] Picker/render divergence — same slug, two identities

- **Why it matters.** `ColorPalette.vue` paints swatches with `background: var(--cat-{slug})` (pastel). Status pills render saturated text on 12% tint using `--sta-*`. In `StatusesModal.vue` admin picks a pastel candy and ships a brick-red pill. Pastel-collision pairs (red/pink, orange/yellow) are hard to tell at pick time but obvious at render.
- **Fix.** Add `variant: 'category' | 'status'` to `ColorPalette.vue`; for status, render swatches as mini pills (saturated text on 12% tint). Slot a live preview next to the color row in both modals.
- **Suggested command.** `/impeccable clarify`

### [P1] Manifesto drift: six status hues on a "one hue" board

- **Why it matters.** `_tokens.scss:2-4` manifesto vs 12 slugs just below. Default statuses: `new=blue, done=green, rejected=red` (`Project::createDefaultStatuses`). `new=blue` collides with the manifesto rule "green marks the current selection". Real board = ~10-14 color-meaning assignments; the anti-reference "Trello loud board" moves closer.
- **Fix.** Shrink the **status** palette to the three semantic positions already in slug vocabulary (`--s-new`, `--s-done`, `--s-rejected`); custom statuses pick a position on that scale, not a hue. Keep full 6-color pastel palette for **categories** only. Default `new.color = null` so neutral reads "nothing happened yet" instead of "blue".
- **Suggested command.** `/impeccable quieter`

### [P1] ColorPalette a11y: half-wired radiogroup, no clear, hard-coded label

- **Why it matters.** `role="radiogroup"` declared at `ColorPalette.vue:2` with these gaps:
  1. `aria-label="Цвет категории"` is hard-coded; `StatusesModal.vue:20` reuses it unchanged → screen-reader says "category color" while editing a status.
  2. No roving tabindex, no Arrow handler. WAI-ARIA pattern requires both.
  3. No `:focus-visible` on `.color-swatch` (`_categories-modal.scss:74-97`) — keyboard focus is invisible on unselected swatches.
  4. No "clear color" affordance; `v-model` is `defineModel<PaletteColor>({ required: true })`. Legacy `color: null` rows get forcibly recolored on open-edit (`CategoriesModal.vue:88`, `StatusesModal.vue:98`).
- **Fix.** `label: string` prop with per-modal defaults; roving tabindex (only selected is tabbable; Arrow Left/Right/Up/Down to move); `:focus-visible` outline distinct from the selected ring; render a seventh "neutral / none" swatch; model type `PaletteColor | null`.
- **Suggested command.** `/impeccable harden`

### [P2] Default = red + no live preview + two pastel pairs collide

- **Why it matters.** Both modals seed new rows with `PALETTE_COLORS[0]` = 'red' (`CategoriesModal.vue:64`, `StatusesModal.vue:74`). Red is also `rejected`-coded. Swatches `red #f3cfc8` vs `pink #efccd4` and `orange #f6d8b3` vs `yellow #efe09f` are near-duplicates in pastel; disambiguation lives only in `title` tooltips (useless on touch, silent on keyboard).
- **Fix.** Seed new rows with `color: null` (force explicit pick). If the P0 preview is in place, pair collision becomes trivial. Otherwise widen the pink and yellow hues so pairs don't collide at swatch size.
- **Suggested command.** `/impeccable distill`

## Persona Red Flags

**Sam (a11y).** No roving tabindex and no Arrow handler — six stops to Save or no in-group navigation. Hard-coded `aria-label="Цвет категории"` is read aloud while editing statuses. No `:focus-visible` means focus on an unselected swatch is invisible. The selected `outline` is the only visible focus signal.

**Jordan (first-timer).** Opens Columns, sees `Новое / Готово / Отклонено` already colored blue/green/red, picker preselects red. No copy explains whether color controls the pill, column header, or drag highlight — only the position `.note`. Why `done` is green but `new` is blue on a brand that teaches "green = your action/selection" everywhere else — semantic read is muddled.

**Casey (mobile).** Swatches 24×24 with 8px gap, below 44×44 touch floor. No tooltip support on phones — pastel-red vs pastel-pink is indistinguishable. The modal reflows cat-rows for `$mobile-mini`, but `.color-palette` only has `flex-wrap: wrap`; no mobile size bump.

## Minor Observations

- Token comment at `_tokens.scss:35` ("each at 5.2:1 or more") is factually wrong for `sta-orange` and `sta-yellow` in light.
- `.pill` / `.type` have no explicit `[data-color="none"]` selector in `_tags.scss` — implicit fallback via default rule.
- `_categories-modal.scss:94-96` `transform: scale(1.08)` on hover has no `transition`, so it snaps.
- `.pill` has no `max-width` or ellipsis; long custom status names will stretch `.col-head` in `KanbanBoard.vue`.
- `<span class="pill" :data-color="post.status.color ?? 'none'">` is duplicated 5 times; one shared `<StatusPill :status>` would centralize fixes.
- On dark theme the swatch border `1px solid var(--line)` and `--cat-green #3b5f49` are close enough that the green swatch reads as a hollow ring.
- `CategoriesModal.vue:71` surfaces `e.field('color')`, but the UI cannot produce an invalid color (fixed enum).

## Questions to Consider

- If the manifesto says "green is the one hue," why do system statuses need colors at all? What if `new / done / rejected` shipped with `color: null` and semantic tokens (`--s-*`) were the identity for defaults — colors become opt-in only for custom columns.
- The picker says "red" and the board also says "red" — but they look like two different colors. Would the honest version split vocabulary (categories: pastel 6; statuses: three semantic accents named `go / wait / stop`) and stop pretending it is one palette?
- If a hardened `ColorPalette` adds roving-tabindex + clear-color, is it reusable enough to extract to `shared/ui/ColorPaletteComponent.vue` the same way `InputComponent` / `SelectComponent` live there?
