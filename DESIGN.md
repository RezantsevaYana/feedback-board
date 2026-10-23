---
name: Feedback Board
description: One calm place for a product's bugs and ideas, built on a single Rocont green.
colors:
  rocont-green: "#1d8547"
  green-ink: "#1b7f41"
  on-green: "#ffffff"
  sprout-active: "#cfe7d7"
  sprout-active-ink: "#063f2c"
  page-white: "#ffffff"
  sage-step: "#e8eee9"
  sage-line: "#d8e1db"
  sage-control: "#a9bcb0"
  deep-ink: "#0f2224"
  moss-muted: "#4c5d60"
  clay-red: "#b0423a"
  on-clay: "#ffffff"
  graphite-bg: "#1f2524"
  graphite-surface: "#3a4442"
  graphite-step: "#2c3433"
typography:
  wordmark:
    fontFamily: "Unbounded, Onest, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 500
    lineHeight: 1.2
  headline:
    fontFamily: "Onest, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "28px"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.015em"
  title:
    fontFamily: "Onest, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "16px"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "Onest, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.5
    fontFeature: "tnum"
  label:
    fontFamily: "Onest, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "13px"
    fontWeight: 500
    lineHeight: 1.4
  meta:
    fontFamily: "Onest, system-ui, -apple-system, Segoe UI, sans-serif"
    fontSize: "12px"
    fontWeight: 500
    lineHeight: 1.4
  mono:
    fontFamily: "ui-monospace, SF Mono, Menlo, Consolas, monospace"
    fontSize: "13px"
    fontWeight: 400
rounded:
  tag: "6px"
  control: "8px"
  surface: "10px"
  full: "999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
components:
  button-primary:
    backgroundColor: "{colors.rocont-green}"
    textColor: "{colors.on-green}"
    rounded: "{rounded.surface}"
    padding: "8px 16px"
  button-secondary:
    backgroundColor: "{colors.page-white}"
    textColor: "{colors.deep-ink}"
    rounded: "{rounded.surface}"
    padding: "8px 16px"
  button-danger:
    backgroundColor: "{colors.page-white}"
    textColor: "{colors.clay-red}"
    rounded: "{rounded.surface}"
    padding: "8px 16px"
  button-danger-armed:
    backgroundColor: "{colors.clay-red}"
    textColor: "{colors.on-clay}"
    rounded: "{rounded.surface}"
    padding: "8px 16px"
  input:
    backgroundColor: "{colors.page-white}"
    textColor: "{colors.deep-ink}"
    rounded: "{rounded.surface}"
    padding: "9px 12px"
  input-search:
    backgroundColor: "{colors.sage-step}"
    textColor: "{colors.deep-ink}"
    rounded: "{rounded.surface}"
    padding: "9px 36px"
  nav-item-active:
    backgroundColor: "{colors.sprout-active}"
    textColor: "{colors.sprout-active-ink}"
    rounded: "{rounded.control}"
    padding: "7px 10px"
  chip:
    backgroundColor: "{colors.sage-step}"
    textColor: "{colors.moss-muted}"
    typography: "{typography.meta}"
    rounded: "{rounded.tag}"
    padding: "1px 8px"
  card:
    backgroundColor: "{colors.page-white}"
    textColor: "{colors.deep-ink}"
    rounded: "{rounded.surface}"
    padding: "18px 20px"
---

# Design System: Feedback Board

## 1. Overview

**Creative North Star: "The Tended Garden"**

The board is a garden someone looks after every day. The ground is plain white with sage tone steps for trays and chips; one green, Rocont Green, grows only where something can be done or has been chosen. Nothing is planted for decoration. A team member opening the queue for the fifth time today should see the same calm beds, with the new shoots (posts in «Новое», your vote, the selected filter) standing out because they are the only green on screen.

Liveliness lives in the small moments, as PRODUCT.md puts it: "reactions, votes, small motion, empty states", not in colour or layout tricks. The 👍 and 💩 vote buttons, a short rise when a menu or toast appears, a friendly empty column ("Всё разобрано"): that is where the personality is allowed. The structure itself stays quiet and familiar so the queue always comes first.

The system rejects the generated "AI look" (gradient blobs, glass panels, icon-in-a-tinted-circle cards, tiny uppercase letter-spaced labels, dashed hint boxes), dull monochrome admin kits with ink-black buttons, the childish green-plus-purple mix of an earlier version, Trello-style multicolour boards, and harsh contrast for its own sake. Right-lane references: Linear for density and precise states, Notion for warm neutrality, Canny and Featurebase for what a feedback board must do.

**Key Characteristics:**
- One hue: Rocont Green marks actions and the current selection, nothing else.
- Depth by sage tone steps and 1px lines; shadows only on floating layers.
- Calm, precise components: 10px corners, hairline edges, green fill only for the main action.
- One body family (Onest); the Unbounded display face is reserved for the wordmark.
- Light and dark themes are both first-class; the embeddable widget stays light.
- Desktop-first breakpoints (1919 / 1439 / 1023 / 767 / 479), fixed side gutters, 1712px max container.

## 2. Colors

A white garden with sage paths and a single green, plus one clay red that only ever means "this destroys or reports a problem".

### Primary
- **Rocont Green** (#1d8547): the primary action fill ("Предложить", "+ Новая доска", "Сохранить"), the selected-tab edge and the brand tag. One step deeper than the brand #27AD5A so white text holds 4.5:1. In dark theme it stays the fill; text-on-dark uses the lighter Green Ink.
- **Green Ink** (#1b7f41; dark theme #6fcf96): links, the search magnifier, hover on names, the focus ring. Green as text, never as a large surface.
- **Sprout Active** (#cfe7d7) with **Sprout Active Ink** (#063f2c, 9.6:1): every selected state. The current nav section, the chosen segment, the active chip. Pale green ground, dark green text, never an ink-filled block.

### Neutral
- **Page White** (#ffffff): page and surfaces in light theme. Cards and inputs sit on the same white, separated by lines, not by colour.
- **Sage Step** (#e8eee9): the one tone step. Kanban column trays, chips, the filled search field, hover rows, the neutral category tag.
- **Sage Line** (#d8e1db): every 1px border and divider.
- **Sage Control** (#a9bcb0): the edge of a selected segment and a hovered search field.
- **Deep Ink** (#0f2224): body text, a step towards Rocont Green Dark (#00474c), never pure black.
- **Moss Muted** (#4c5d60): secondary text, hints, meta (6.3:1 on white, 5.6:1 on Sage Step).
- **Graphite** (bg #1f2524, step #2c3433, surface #3a4442): dark theme. Three clear lightness steps with a trace of green; page, then trays, then cards and inputs as the lightest layer.

### Semantic
- **Clay Red** (#b0423a; dark theme #efb0a6): destructive actions, errors, high priority. Red text on a quiet outline at rest; filled (with #ffffff text, dark-theme #3b1410) only when a dialog's destructive answer is armed.
- **Category pastels** (red #f3cfc8, orange #f6d8b3, yellow #efe09f, green #cce4d1, blue #c9dde6, pink #efccd4): category tag grounds chosen per category by the team. Text on them stays Deep Ink.
- **Status inks** (red #8f3029, orange #7f4718, yellow #6b531a, green #14623a, blue #2a4e6a, pink #8a3059): status pills as text on a 12% tint of the same ink.

### Named Rules
**The One Green Rule.** Green means "act here" or "you chose this". If a green element on screen is neither an action nor a selection, remove the green.

**The Sage Not Grey Rule.** Every neutral leans toward the brand green. Pure greys (#888, #ccc) and pure black are forbidden.

**The Quiet Danger Rule.** Clay Red is an outline until the moment of no return. Only an armed confirm inside a dialog gets a red fill.

## 3. Typography

**Display Font:** Unbounded (with Onest, system-ui)
**Body Font:** Onest (with system-ui, -apple-system, Segoe UI)
**Label/Mono Font:** ui-monospace (SF Mono, Menlo, Consolas), for slugs, URLs and ids only

**Character:** Onest is a warm, rounded grotesque that carries every heading, label and number in one family; Unbounded's wide geometric letters appear only in the wordmark and the slanted brand tag, as the garden's signboard.

### Hierarchy
- **Wordmark** (Unbounded 500, 15px on the board tag, 18px in the admin header): the project name tag and "Feedback Board". Nowhere else.
- **Headline** (Onest 600, 28px, line-height 1.2, letter-spacing -0.015em; 22–24px on admin pages and on phones): one per page ("Предложения", "Мои доски").
- **Title** (Onest 600, 16px, 1.3): section and card headings ("Общее", "Корзина", board names on cards).
- **Body** (Onest 400, 15px, 1.5, tabular numbers): reading text, card titles, form values. Prose capped near 70ch.
- **Label** (Onest 500, 13px): field labels, link buttons, secondary navigation.
- **Meta** (Onest 500–600, 12px): chips, status pills, category tags, notes and hints.

### Named Rules
**The Signboard Rule.** Unbounded is the sign on the gate. It never appears in buttons, labels, data or headings.

**The Sentence Case Rule.** No uppercase letter-spaced micro labels. Labels are sentence case, weight does the work.

## 4. Elevation

The system is flat. Depth comes from tonal layering: white surfaces on a white page are separated by Sage Line hairlines, and grouped content sits in Sage Step trays. A single ambient shadow exists for things that float above the page and must read as temporary.

### Shadow Vocabulary
- **Float** (`box-shadow: 0 10px 28px rgba(16, 37, 40, 0.12)`; dark `0 10px 30px rgba(0, 0, 0, 0.5)`): dropdown menus, toasts, dialogs. Nothing that stays on the page.
- **Focus halo** (`box-shadow: 0 0 0 3px #cfe7d7`): the search field's focus ring only. A state, not elevation.

### Named Rules
**The Flat-At-Rest Rule.** Cards, columns and sections never carry a shadow. If a resting element needs to stand out, give it a tone step or a line, not a shadow.

## 5. Components

Calm and precise: hairline edges, 10px corners, one green fill per view.

### Buttons
- **Shape:** gently rounded (10px), 1px Sage Line edge, 8px × 16px padding, Onest 500.
- **Primary:** Rocont Green fill, white text, 600 weight. One per view: the action the screen exists for.
- **Secondary:** white with a Sage Line edge and Deep Ink text. Hover darkens the edge toward ink.
- **Danger:** white with Clay Red text at rest; inside a dialog, once armed, filled Clay Red with white text. Disabled stays the quiet outline at 60% opacity.
- **Busy:** `aria-busy` shows a 14px spinner before the label, the button keeps full strength.
- **Link button:** 13px Moss Muted text with a Sage Line underline, ink on hover; for low-weight actions on cards and lists ("Настройки", "Переименовать").

### Chips and Tags
- **Chip:** Sage Step ground, Moss Muted 12px text, 6px corners ("базовый", counters).
- **Status pill:** the status ink as text on a 12% tint of itself, 6px corners, ellipsised when long.
- **Category tag:** the brand slant shape (left edge cut by `clip-path`, corners 4px 10px 10px 4px) on a pastel ground chosen per category, Deep Ink 12px 600 text.

### Cards / Containers
- **Corner Style:** 10px.
- **Background:** Page White (Graphite surface in dark), Sage Step for trays.
- **Shadow Strategy:** none at rest (see Elevation).
- **Border:** 1px Sage Line; hover tints the edge toward green (50% Rocont Green mix).
- **Internal Padding:** 18px × 20px on cards and settings sections, 14px on phones.

### Inputs / Fields
- **Style:** white, 1px Sage Line, 10px corners, 9px × 12px padding. The component owns label (13px 500), hint ("· hint", 12px muted) and error.
- **Focus:** green edge plus a 2px green focus outline offset by 2px.
- **Error:** Clay Red edge, the message below in Clay Red, linked to the field with `aria-describedby`.
- **Search:** filled Sage Step field without an edge, green magnifier, «/» key hint; on focus it turns white with a green edge and the Sprout halo.

### Navigation
- **Side nav (board, settings):** 14px Moss Muted items with 8px corners; hover shows a Sage Step ground; the current item gets the Sprout Active ground with Sprout Active Ink text and a green edge.
- **Tablet and phone:** the side nav becomes a row: a segmented control on the board, a scrollable row of outlined chips in settings, with overflow sections behind «Ещё».
- **Admin header:** wordmark, then «/» and the board switcher (a quiet text button with a chevron), email and «Выход» on the right. Below 480px it becomes two rows with the switcher across the full width.

### The Brand Tag (signature)
The project name sits in a Rocont Green slanted tag set in Unbounded: the board's signboard. In team mode with several boards the tag is the board switcher's trigger, keeping the green fill and adding a chevron.

### Menus and Dialogs
- **Menu:** white list, 10px corners, Float shadow, 4px inset, 14px items; the current item is bold with a green check. Rows grow to 44px on touch screens.
- **Dialog:** bottom sheet on phones (swipe down to close), centred panel elsewhere; focus moves in on open, Tab stays inside, Esc closes only the topmost one.
- **Motion:** short rise (`translateY(8px)` to 0, 0.14–0.18s, ease-out); hover and colour transitions 0.12s; every motion is off under `prefers-reduced-motion`.

## 6. Do's and Don'ts

### Do:
- **Do** keep Rocont Green (#1d8547) to actions and the current selection; one green fill per view.
- **Do** show selection as Sprout Active (#cfe7d7) with Sprout Active Ink (#063f2c) text, never an ink-filled block.
- **Do** build depth from Sage Step (#e8eee9) trays and Sage Line (#d8e1db) hairlines.
- **Do** use the 10px radius for buttons, inputs, cards and sections; 6px for chips and pills; the slant shape only for the brand and category tags.
- **Do** give every status a text label, not only a colour.
- **Do** define every new colour as a token in both themes; the widget's `:host` reads the same tokens.
- **Do** keep text at WCAG AA (4.5:1) in both themes and keep a visible 2px green focus outline.
- **Do** put personality into reactions, votes, empty states and small motion.

### Don't:
- **Don't** use the generated "AI look": gradient blobs, glass panels, icon-in-a-tinted-circle cards, tiny uppercase letter-spaced labels, dashed hint boxes.
- **Don't** fall into dull monochrome admin kits: grey ground, ink-black buttons, no colour anywhere.
- **Don't** bring back childish, toy-like colour: no purple or a second hue next to the green ("one hue, not two").
- **Don't** make it look like Trello: saturated multicolour labels and loud boards.
- **Don't** use harsh contrast for its own sake.
- **Don't** put a shadow on a resting card, column or section.
- **Don't** use Unbounded outside the wordmark and brand tag.
- **Don't** use `border-left` or `border-right` wider than 1px as a coloured accent stripe.
- **Don't** fill a destructive button red until the user has confirmed what they are destroying.
- **Don't** use pure black, pure grey, or a raw hex inside a component; go through the tokens.
