# Changelog

Notable changes to `bladewell/bladewell`, by widget. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## Updating

A release changes nothing in your app until you run `php artisan bladewell:add --installed`. It updates the files you haven't edited and lists the ones you have; `php artisan bladewell:diff` shows what those would miss.

Component names, props and their values, `data-*` hooks, JS exports, the `bladewell:*` commands and flags, and config keys are only ever added to, never renamed or removed. A release can still reject input that never worked (a mistyped value that was ignored now throws); those changes are under **Changed**.

## [Unreleased]

## [1.4.6] - 2026-10-31

### Added

- **tooltip, tooltip-cursor**: on a touch screen, a long press on a button or link shows its tooltip without pressing it; a tap still presses. iOS's link preview is off on these elements.
- **tooltip-cursor**: on a touch screen, hold a chart bar and slide to read each bar without scrolling the page.

### Fixed

- **tooltip, tooltip-cursor**: a tooltip opened by a finger shows above it, not under the finger.

## [1.4.5] - 2026-10-30

### Added

- **kanban**: cards drag by touch: hold a finger on a card, then drag. A swipe still scrolls, and moves are saved as before.

## [1.4.4] - 2026-10-28

### Added

- **kanban**: a new widget, `php artisan bladewell:add kanban`. Columns of your own cards, moved by a Move menu, the keyboard, a tap or a mouse drag. Moves are announced, can be undone, fire `kanban:move`, and with `move-url` are saved to your route, which can refuse them. Readonly boards and columns, locked cards, and `wire:kanban-move` in Livewire.
- **captcha**: Turnstile, reCAPTCHA and hCaptcha work with `wire:model`. The token is set when issued and cleared when it expires, and a form that fails validation gets a new one.

## [1.4.3] - 2026-10-27

### Fixed

- **toast**: the layout usage note gave the default position as top-center; it's top-right.
- The Laravel Boost guidelines tell your agent not to wrap widgets in `wire:ignore`, and to set a control's value from the server or Livewire, not a script. Run `php artisan boost:update`.

## [1.4.2] - 2026-10-27

### Added

- **tags**: `layout="below"` keeps the box to one line and lists the tags under it.

### Fixed

- **chat**: sending while scrolled up takes you to your message, and a reply that arrives meanwhile shows too.
- **dropdown, popover**: a panel opened after the window grew stays on screen. Pressing the same letter again in a dropdown moves to the next item starting with it.
- **dropdown, popover, command**: in a `disable-close` modal, Escape closes the menu, popover or palette on top and returns focus.
- **command**: disabled items are greyed out and can't be run; stale results can't be chosen mid-search; no spinner is left after closing.
- **otp**: a pasted code fills from the first box, deleting in the middle closes the gap, and digits from any keyboard are read. **verification-code** reads them too.
- **tags**: a readonly field keeps its tags, and a paste over selected text replaces it.
- **search**: clearing the box clears the last suggestions.
- **color-picker**: emptying the hex box clears the swatch and preview.
- **number**: a readonly stepper ignores the arrow keys.
- **captcha**: showing another code empties the answer for `wire:model` too.
- **All widgets in a Livewire component** (`ElementIds`): `Livewire::test()` no longer throws "Duplicate element id" for a widget with its own `id`.
- **tabs** (`remember`): an unreadable link entry no longer stops the other remembered tabs opening.
- **stepper**: with no steps it draws nothing.
- **toast**: a tall stack scrolls, and an updated toast keeps focus on its close button.
- **time-picker**: Ctrl, Cmd and Alt shortcuts go to the browser, so Ctrl+A no longer switches a `segmented` time to AM.
- **tooltip, tooltip-cursor**: in a modal, Escape closes the tooltip first. Both stay placed after a resize or a late font.
- **tree**: open nodes stay open through a Livewire render, and checkbox parents show the right state at any depth.

## [1.4.1] - 2026-10-25

### Fixed

- **`bladewell:add --force`** and the MCP `force` option overwrite only the widgets you name, not `theme.css`, `base.css` or shared widgets such as `field`.
- **chat**: line breaks survive the server's copy of a message; a refused message stays as Not sent with Edit; Livewire errors show under the box; older messages that fail to load offer Try again.
- **file-upload**: a rejected image restores the saved one, Retry respects `max-files`, and resetting the form empties the widget.
- **select**: readonly `multiple` selects keep their choices; with `search-url`, only the latest search can show an error and added options stay.
- **datepicker, date-range-picker**: without `min` or `max` the calendar has no limit; changed limits apply on the next open; 29 February counts as 28 February for `birthday` and `MinimumAge`.
- **table**: with several tables on a page, changing one leaves the others alone, and a failed filter can be retried.
- **modal**: Escape closes only the top modal, and `reset-on-close` resets any form.
- **time-picker**: a `segmented` time can be edited part by part, its arrows follow `step`, and typing 1 in a 12-hour list goes to 1:00.
- **number**: reads digits from any keyboard, and a stepper without `min` or `negative` stops at 0.
- **phone**: reads digits from any keyboard, accepts a leading 00, and drops the national prefix for Russia, Kazakhstan and Hungary, in the browser and in `Countries::international()`.
- **`bladewell:*` commands**: work when `composer.json` maps a namespace to several directories. `bladewell:mcp` answers batched requests.

## [1.4.0] - 2026-10-24

### Added

- **sidebar-layout**: a new widget, an app frame with a sidebar (brand, accordion menu, footer), a fixed top bar and a scrolling page. On large screens it collapses to a rail of icons (`collapsed`, and `remember` to keep the choice in a cookie); on phones it slides in over the page. A `panel` slot adds a side panel such as a chat. RTL and reduced motion supported.
- **accordion**: an `icon` prop on `accordion.menu-item` and menu groups, shown in a collapsed sidebar-layout.
- **icon**: `panel-left`.
- **chat**: a new widget. `variant="assistant"` for AI replies (Markdown, code blocks, `streaming`) and `variant="messages"` for bubbles with names, avatars, times and delivery state. It opens at the newest message, keeps your place when new ones arrive, and loads older ones from `history-url`. Parts: `chat.message`, `chat.attachment`, `chat.divider`, `chat.typing` and `chat.composer` (Enter to send, attachments, `async` sending, `maxlength`). `compact` for narrow columns. Works with `wire:submit`, `wire:model` and `wire:stream`.
- **auth-layout**: a new widget for sign-in, sign-up and password pages, with a `title`, `subtitle`, form and footer. `variant="split"` puts a brand panel beside the form; `variant="centered"` puts it on a card. No script.

### Fixed

- **accordion** (`variant="menu"`): closing a group while scrolled no longer leaves empty space and a useless scrollbar.

## [1.3.3] - 2026-10-22

### Changed

- **Theme**: `--color-field` darkens to `#ebedf1` and `--color-muted` to `#5d6679`, so fields stand out and hints keep 4.5:1. Change these in your copied `theme.css`; `bladewell:add --installed` doesn't.
- **All widgets**: secondary text goes from `text-foreground/60` to `/70`, to keep 4.5:1 on the darker field.

## [1.3.2] - 2026-10-14

### Changed

- **MCP server** (`bladewell:mcp`): results are compact JSON, using fewer tokens.

## [1.3.1] - 2026-10-13

### Changed

- **Laravel Boost guidelines**: point your agent to `bladewell:list --json` to find widgets. Run `php artisan boost:update`.

## [1.3.0] - 2026-10-13

### Changed

- **`bladewell:add`** with no names shows a searchable list to pick from (plain text on Windows).
- **`bladewell:list`** shows each widget and what it also installs, without descriptions; those are in `--json`.
- **`bladewell:add`** with a mistyped name suggests the closest one and reports every unknown name. Exit codes are unchanged.
- The package requires `laravel/prompts` (`^0.3`), which every Laravel 12 and 13 app already has.

## [1.2.2] - 2026-10-13

### Changed

- **Laravel Boost guidelines**: widget URLs (`suggest-url`, `search-url`, `children-url`, `upload-url`) go in `routes/web.php`, not `routes/api.php`.
- **search, command**: suggestions open only `http`, `https`, same-site, `mailto:` and `tel:` links; others log a warning.
- **tree**: `children-url` must be on your own site.

## [1.2.1] - 2026-10-12

### Fixed

- **All form fields** (`base.css`): autofilled text follows a switch between light and dark at once.

## [1.2.0] - 2026-10-12

### Added

- Guidelines for [Laravel Boost](https://github.com/laravel/boost) in `resources/boost/guidelines`: include `bladewell/bladewell` in `boost:install` and your agent learns how to find and add widgets.

## [1.1.0] - 2026-10-11

### Added

- **date-range-picker**: on a touch screen, press a start day and slide to the end. Two taps still work, and slides elsewhere scroll.
- **date-range-picker**: a tap beside a day's button picks that day.
- **button**: `confirm`, with `confirm-message`, `confirm-label` and `confirm-cancel`, asks before it submits, links or runs `wire:click`.
- **toast**: a `data-toast` hook shows a toast on click without a script (`data-toast-message`, `data-toast-title`; `data-toast="dismiss"` clears them).
- **select, tags, datepicker, date-range-picker, time-picker, color-picker, slider, otp, checkbox, switch, radio**: a `readonly` prop: the value shows and submits but can't be changed. `disabled` wins over it, and `wire:model` still binds.
- **otp**: an `info` hint under the boxes.
- **popover, tabs, tree, clock, command**: their script APIs are also on `window` (`window.popover`…) and take an id or an element.
- **dropdown**: `dropdown.open()`, `close()` and `toggle()`, also on `window.dropdown`, and `dropdown-open` / `dropdown-close` from Livewire.
- **modal, popover, dropdown, tabs, tree, clock, command, progress**: a script call or Livewire event for a target that isn't on the page logs a warning.
- **otp, select, textarea**: events also sent as `otp:complete`, `select:change` and `textarea:resize`.
- **verification-code**: a `verification-code:complete` event with the code.
- **dropdown**: a shared menu for long tables: `<x-widget.dropdown.menu id="…">` once, and `<x-widget.dropdown menu="…" :values="[…]">` per row. `:name` placeholders take each row's values and `only-if` hides items per row. 100 rows go from 564 KB to 251 KB of HTML. New hooks: `data-dropdown-values`, `data-dropdown-shared`, `data-dropdown-only-if`.
- **table**: the `compact` example uses the shared row menu.
- **datepicker**: a `future` example (`min="today" :max="false"`).

### Changed

- **select, search, command, table**: a prop name from one of the others now throws and names the right one (such as `suggest-url` on select, which takes `search-url`), instead of doing nothing.
- **dropdown, popover**: an icon-only trigger without `label` throws, naming the dropdown or popover.

## [1.0.0] - 2026-10-09

### Added

- **modal**: bottom sheets (`variant="drawer" side="bottom"`, `mobile="sheet"`) swipe down to close when the backdrop may close them. New hook: `data-modal-sheet`.
- **pagination** (`drawer`, and the table's page sheet): swipes down to close too.
- **tooltip, tooltip-cursor**: on a touch screen, a tap shows the tooltip on plain text, icons and chart bars; the next tap hides it.
- **All widgets** (`base.css`): `tap-target` and `tap-target-y` give small controls a 44px tap area on touch screens without drawing them bigger.
- **radio**: `variant="segmented"`, a single track for two to four short choices.
- **field**: `visibleArea()` and `placeBeside()` exports, for placing popovers clear of the on-screen keyboard.
- **color-picker**: a new form control: a hex box, the browser's picker (`custom`) and optional `swatches`. Works with `wire:model`.
- **command**: a new widget, a command palette opened with Ctrl+K or Cmd+K. Groups and items, search by label or keywords, `suggest-url` for your app's results, and `recent`.
- **icon**: 40 more icons for apps, commerce, editing and media.
- **popover**: a new widget, a panel beside a button that flips when there's no room and handles focus. `title`, `width` and `align`; `popover.open()`, `close()` and `toggle()`; `popover-open` / `popover-close` from Livewire.
- **slider**: a new form control, one thumb or a `range` of two, with `min`, `max`, `step`, `prefix` and `suffix`. Works with `wire:model`.
- **tags**: a new form control for free-text tags, with `suggestions` and `max`. Submits as `name[]` and works with `wire:model`.
- **tree**: a new widget with four looks (`list`, `cards`, `org`, `checkbox`), lazy children from `children-url`, and full keyboard support. Styled through `--tree-line`, `--tree-indent` and `--tree-gap`.

### Changed

- **modal, alert, toast, datepicker, date-range-picker**: close buttons and month arrows get a 44px tap area on touch screens (`tap-target`).
- **password, number, captcha, file-upload, select, pagination**: small buttons get a 44px tap height on touch screens (`tap-target-y`).
- **price-roll**: the change badge wraps instead of running off a phone's edge.
- **pagination** (`jump`): the on-screen keyboard's return key reads Go.

### Fixed

- **modal, toast, pagination**: drawers, sheets and toasts stay clear of the notch and home indicator under `viewport-fit=cover`.
- **search, select, phone, datepicker, date-range-picker, time-picker**: lists and calendars open above the field when the on-screen keyboard leaves no room below.

## [0.3.0] - 2026-10-06

### Changed

- **All widgets**: LarawellUI is now **Bladewell**. This release renames the package, commands, config and lock file, with no aliases; widgets and their props, hooks and copied code are unchanged. To upgrade:
    1. `composer remove --dev larawellui/larawellui && composer require --dev bladewell/bladewell`
    2. Rename `config/larawellui.php` to `config/bladewell.php`, if you published it.
    3. Rename `larawellui.lock` to `bladewell.lock` **before** running any command, or `bladewell:add --installed` will overwrite your edited files.
    4. Use `php artisan bladewell:add | list | diff | mcp` instead of `larawell:*`, and update your MCP client's config.
    5. The site is now <https://www.bladewellui.com>.

## [0.2.3] - 2026-10-04

### Added

- **icon**: names outside the built-in set are drawn by Blade Icons (`blade-ui-kit/blade-icons`) when it's installed, in every widget's icon props.

## [0.2.2] - 2026-10-04

### Added

- **All widgets** (`base.css`): a `data-theme-changing` hook on `<html>` holds back colour transitions while the theme switches.

### Changed

- **button** (`<x-widget.button.back>`): the arrow sits closer to its label (`gap-1.5`).

### Fixed

- **table**: changing page, sort or filter no longer reports CSP violations for the fetched page's inline styles.
- **PHP helpers and validation rules**: written in Pint's default style, so running Pint no longer marks them as edited.

## [0.2.1] - 2026-10-04

### Added

- **field**: a `data-field-info` hook on the hint.

### Fixed

- **All form widgets**: a field with an error is described by the error alone; the hint returns once it clears.
- **table**: the sortable example no longer logs a PHP deprecation.

## [0.2.0] - 2026-10-03

### Added

- **show-if**: a new widget that shows part of a form only while another field has a given value. Hidden fields don't submit or block the form.
- **breadcrumbs**: a new widget, a trail of links to the current page; one link back on phones.
- **search**: suggestions as you type from `suggest-url`, with details, links and groups. `suggest-min` and `messages` tune it.
- **select**: `search-url` searches your app as you type, single or multiple, with `wire:model`. `search-min-length`, `searching` and `search-failed` tune it.
- **All widgets**: a dark theme at WCAG AA, with `class="dark"` or `data-theme="dark"`.
- **Theme**: `primary-fill`, `error-fill` and `success-fill` for backgrounds under white text; `primary`, `error` and `success` are for text, borders and tints.
- **icon**: 24 more icons.
- **All widgets**: `examples-use` in a manifest names the other widgets its examples need.
- **All widgets**: every prop is described on its page, in `/r/*.json` and through MCP.

### Changed

- **button, checkbox, accordion, datepicker, date-range-picker, modal, pagination, progress, stepper, tabs, time-picker**: white text sits on the new fill colours.
- **All widgets**: a prop value that isn't allowed (`variant="primay"`) now throws and lists the allowed ones. See "Values from your data" in the README.
- **button**: `type` takes only button, submit or reset.
- **dropdown**: an item's `method` takes only GET, POST, PUT, PATCH or DELETE.
- **file-upload**: `uploaded` needs `upload-url`.
- **modal**: `size="full"` on a drawer, and `mobile="sheet"` on a drawer or full-screen dialog, now throw.
- **table**: a row with both `href` and details throws; `bulk-method` takes only GET, POST, PUT, PATCH or DELETE.

### Fixed

- **field**: required checks skip fields in a disabled fieldset.
- **time-picker**: the list and columns are reachable with Tab and arrow keys.
- **table**: expandable rows open from a real Details button with `aria-expanded`.
- **tooltip**: around an icon with no text, the wrapper is an image named by the tooltip (`role="img"`).
- **file-upload**: inputs no longer get two labels.
- **progress, search**: examples pass contrast in dark mode.
- **clock**: the countdown no longer gets an empty `aria-label`.
- **icon, clock, pagination**: missing props added to the docs, `/r/*.json` and MCP.
- **stepper**: the bars stepper accepts the same step arrays as the others.

## [0.1.0] - 2026-10-02

### Added

- 34 widgets: accordion, alert, button, captcha, checkbox, clock, date-range-picker, datepicker, dropdown, field, file-upload, icon, modal, number, otp, pagination, password, phone, price-roll, progress, radio, search, select, stepper, switch, table, tabs, text-input, textarea, time-picker, toast, tooltip, tooltip-cursor and verification-code.
- `bladewell:add`, which copies widgets and what they need into your app, with `--dry-run`, `--installed` and `--force`.
- `bladewell:list`, with `--json`.
- `bladewell:diff`, showing how your edited copies differ from the package.
- `bladewell:mcp`, an MCP server for AI agents.
- `bladewell.lock`, so updates replace only files you haven't edited.
- Livewire 3 and 4 support, with `wire:model` on every form control.

[Unreleased]: https://github.com/rkwebforge/bladewell/compare/v1.4.6...HEAD
[1.4.6]: https://github.com/rkwebforge/bladewell/compare/v1.4.5...v1.4.6
[1.4.5]: https://github.com/rkwebforge/bladewell/compare/v1.4.4...v1.4.5
[1.4.4]: https://github.com/rkwebforge/bladewell/compare/v1.4.3...v1.4.4
[1.4.3]: https://github.com/rkwebforge/bladewell/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/rkwebforge/bladewell/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/rkwebforge/bladewell/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/rkwebforge/bladewell/compare/v1.3.3...v1.4.0
[1.3.3]: https://github.com/rkwebforge/bladewell/compare/v1.3.2...v1.3.3
[1.3.2]: https://github.com/rkwebforge/bladewell/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/rkwebforge/bladewell/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/rkwebforge/bladewell/compare/v1.2.2...v1.3.0
[1.2.2]: https://github.com/rkwebforge/bladewell/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/rkwebforge/bladewell/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/rkwebforge/bladewell/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/rkwebforge/bladewell/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/rkwebforge/bladewell/compare/v0.3.0...v1.0.0
[0.3.0]: https://github.com/rkwebforge/bladewell/compare/v0.2.3...v0.3.0
[0.2.3]: https://github.com/rkwebforge/bladewell/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/rkwebforge/bladewell/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/rkwebforge/bladewell/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/rkwebforge/bladewell/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/rkwebforge/bladewell/releases/tag/v0.1.0
