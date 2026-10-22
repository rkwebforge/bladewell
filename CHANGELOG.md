# Changelog

All notable changes to `bladewell/bladewell` are listed here, by widget, so you can see what an update brings to the ones you use. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## Updating

Widgets are copied into your app, so a new release changes nothing until you run `php artisan bladewell:add --installed`. That updates the files you haven't edited and lists the ones you have; `php artisan bladewell:diff` shows what those would miss.

What you build on is only ever added to, never renamed or removed: component names, props and the values they take, `data-*` hooks, JS exports, the `bladewell:*` commands and their flags, and the config keys. A release can still get stricter about input that never worked, such as a mistyped value that used to be ignored and now throws. Those changes are listed under **Changed**, with the widgets they affect.

## [Unreleased]

### Changed

- **Theme**: input backgrounds (`--color-field`) are a shade darker, `#ebedf1` instead of `#f5f6f8`, so fields stand out from the card behind them on dim or washed-out screens. `--color-muted` darkens to `#5d6679` to keep placeholders and hints at 4.5:1 on the new fill. Your copied `theme.css` isn't updated by `bladewell:add --installed`; change these two values there to get it.
- **All widgets**: secondary text (counts, hints, meta, labels in progress, clock, select, stepper, table and the rest) goes from `text-foreground/60` to `text-foreground/70`, so it stays at 4.5:1 on the darker field.

## [1.3.2] - 2026-10-14

### Changed

- **MCP server** (`bladewell:mcp`): tool results are sent as compact JSON, without indentation, so they use fewer of your agent's tokens. The data is the same.

## [1.3.1] - 2026-10-13

### Changed

- **Laravel Boost guidelines**: they point your agent to `php artisan bladewell:list --json` to find a widget, which says what each one does. Since 1.3.0, plain `bladewell:list` shows only names and what each one also installs. Run `php artisan boost:update` to get the new wording.

## [1.3.0] - 2026-10-13

### Changed

- **`bladewell:add`**, run with no names, shows a list to select as many as you like from: type to narrow it, Space to select, Enter to install, Ctrl+C to quit. What a widget needs still comes along by itself. It used to ask for names typed in a comma-separated line, under descriptions long enough to wrap. On Windows it still asks in plain text.
- **`bladewell:list`** shows each widget and what it also installs (the column was "Requires", which read as something to install yourself), without descriptions, so the table fits your terminal instead of running hundreds of characters wide. Descriptions are in `bladewell:list --json` and on <https://www.bladewellui.com>.
- **`bladewell:add`** with a mistyped name suggests the closest widget ("Unknown widget [datepiker]. Did you mean datepicker?") and where to see the rest, instead of listing all of them in one line. Every unknown name is reported, not just the first. The MCP server still answers with the full list, and the exit code is unchanged.
- The package now lists `laravel/prompts` (`^0.3`) as a dependency. Every Laravel 12 and 13 app already has it, so nothing new gets installed.

## [1.2.2] - 2026-10-13

### Changed

- **Laravel Boost guidelines**: they tell your agent that the URLs a widget calls (`suggest-url`, `search-url`, `children-url`, `upload-url`) are named routes in `routes/web.php`, not `routes/api.php`, so they share the page's sign-in and CSRF protection.

### Security

- **search**, **command**: a suggestion or item whose link isn't a page (`http`, `https`, or a link within your site), an email (`mailto:`) or a phone number (`tel:`) is no longer opened. A `javascript:` or `data:` link in your endpoint's answer, from a URL someone typed into your app, would have run as code in the page; it now logs a warning instead.
- **tree**: `children-url` must be on your own site, as the table's and pagination's pages already had to be. Another site's HTML could carry script into the page; such a URL is now refused before anything is fetched, and shows as a load that failed. This is stricter than before for a `children-url` on another site, which could load if that site allowed it.

## [1.2.1] - 2026-10-12

### Fixed

- **All form fields** (`base.css`): text the browser autofilled now follows a switch between light and dark at once. It kept the colour it had when it was filled, so after a switch it could be light text on a light field until the page was reloaded. In an app, `php artisan bladewell:add --installed` updates `base.css`.

## [1.2.0] - 2026-10-12

### Added

- Guidelines for [Laravel Boost](https://github.com/laravel/boost), in `resources/boost/guidelines`: tick `bladewell/bladewell` when `php artisan boost:install` or `boost:update` asks which packages to include, and your agent's rules (`CLAUDE.md`, `AGENTS.md`…) say how to find and add widgets and what not to do with them: no Alpine or inline scripts, `readonly` rather than `disabled`, `confirm` on buttons, toasts, the shared row menu, and dates.

## [1.1.0] - 2026-10-11

### Added

- **date-range-picker**: on a touch screen, a slide picks the range: press the start day, slide, and the band follows the finger as it follows the mouse; lifting it on a day makes that the end. Two taps still work as before, and a finger that wobbles a little as it taps still taps. A slide that starts on a day picks rather than scrolls the calendar; it still scrolls from anywhere else.
- **date-range-picker**: a tap that lands in a day's cell beside its round button picks that day, rather than nothing.
- **button**: `confirm="…"`, with `confirm-message`, `confirm-label` and `confirm-cancel`, as on the dropdown's items: the question is asked first, and the button only submits, follows its link or runs `wire:click` once it's confirmed. A danger button asks in red. It asks in the modal's dialog when the app has the modal widget and in the browser's own box when it doesn't, so the button still requires only the icon.
- **toast**: a `data-toast` hook: `data-toast="success"` and `data-toast-message="Saved."` (and `data-toast-title`) on any element show that toast when it's clicked, with no script; `data-toast="dismiss"` clears them all. An unknown kind logs a warning. The `try-it` example uses it.
- **select**, **tags**, **datepicker**, **date-range-picker**, **time-picker**, **color-picker**, **slider**, **otp**, **checkbox**, **switch**, **radio**: a `readonly` prop, as the text controls already had: the value shows, can be focused and read out, and is submitted, but can't be changed, and the field takes the same muted look. Select's list, the pickers' calendars and lists, and the colour picker's own picker and swatches don't open or show; tags lose their remove buttons and can't be typed in; a slider's thumbs, the time picker's parts and the OTP boxes ignore the keys and the pointer; checkboxes, switches and radios keep their state when clicked or pressed. Screen readers hear it through `aria-readonly` where the role takes it, and as "read only" in the date and time buttons' names. With `disabled` too, `disabled` wins. `wire:model` still binds: only the user is stopped.
- **otp**: an `info` hint under the boxes, which they are described by, as on the verification code.
- **popover**, **tabs**, **tree**, **clock**, **command**: their scripts' APIs are also on `window` (`window.popover`, `window.tabs`, `window.tree`, `window.clock`, `window.command`), as `modal`, `toast` and `progress` already were, so a script on the page can call them without an import. Each takes an id or an element: `popover.open()` and the rest also take the trigger, panel or wrapper element; `tabs.open()` the tabs element as well as their id; `tree.open()` and `tree.close()` an item's id as well as its element; `clock.start()`, `pause()` and `reset()` a stopwatch's id, or anything inside it, as well as the element. What worked before works the same. Each has a new `in-scripts` usage note.
- **dropdown**: a script API, `dropdown.open(target)`, `dropdown.close(target)` and `dropdown.toggle(target)`, by the trigger's id or element, also on `window.dropdown`; for a shared menu, give the row's trigger. From Livewire: `$this->dispatch('dropdown-open', id: '…')` and `'dropdown-close'`, as on the popover.
- **modal**, **popover**, **dropdown**, **tabs**, **tree**, **clock**, **command**, **progress**: a script call (or a Livewire `*-open`/`*-close` event) whose target isn't on the page logs a `console.warn` naming the widget, instead of doing nothing without a word. Nothing throws.
- **otp**, **select**, **textarea**: their events are also sent under the colon names the other widgets use: `otp:complete`, `select:change` and `textarea:resize`, with the same details. `otp-complete`, `select-change` and `textarea-resize` are still sent too.
- **verification-code**: a `verification-code:complete` event once the field holds a whole code, with the code as `detail.value`, for a form that submits itself, as the OTP has.
- **dropdown**: a shared menu for long tables and lists. Render `<x-widget.dropdown.menu id="…">` once with the items, and in each row only `<x-widget.dropdown menu="…" :values="['id' => $order->id]">`, its trigger. As the menu opens from a row, `:name` placeholders in its links, form actions, `confirm` questions, `data-*` attributes and text take that row's values, so `route('orders.edit', ':id')` becomes the row's own link; `only-if="can_delete"` on an item or divider hides it for rows where that value is false. In the compact table example with 100 rows, that is one copy of the menu instead of 100: 564 KB of HTML becomes 251 KB, rendered in 13 ms instead of 31. Without `menu`, a dropdown renders as before. Placeholders are never filled into `wire:click`, so items that call a Livewire component keep a menu per row. New hooks: `data-dropdown-values` on the row's wrapper, `data-dropdown-shared` on the menu and `data-dropdown-only-if` on items. A trigger whose menu isn't on the page logs a warning.
- **table**: the `compact` example uses the shared row menu.
- **datepicker**: a `future` example for deadlines and appointments: from today with no upper limit (`min="today" :max="false"`). The basic example now says the calendar stops at today by default.

### Changed

- **select**, **search**, **command**, **table**: a prop another of these widgets names differently now fails, naming this widget's own, instead of passing through as an attribute that did nothing: `suggest-url`, `suggest-min` and `empty` on select (it takes `search-url`, `search-min-length` and `no-results`), `search-url` and `search-min-length` on search (`suggest-url`, `suggest-min`), `search-url` and `no-results` on command (`suggest-url`, `empty`), `no-results` on table (`empty`), and `pagination="load-more"` on table (`infinite`).
- **dropdown**, **popover**: an icon-only trigger without a `label` now fails naming the dropdown or popover that was written, instead of the button inside it.

## [1.0.0] - 2026-10-09

### Added

- **modal**: a bottom sheet (`variant="drawer" side="bottom"`, or `mobile="sheet"` on phones) can be swiped down to close, as long as the backdrop may close it too (`close-on-backdrop`, without `disable-close`). It follows the finger and closes past a third of its height or on a flick, and springs back otherwise; a list scrolled down inside it scrolls back up first. A small handle at the top shows it can be dragged. The panel that slides carries a new `data-modal-sheet` hook.
- **pagination** (`drawer`, and the table's page sheet): the page sheet swipes down to close the same way, with the same handle.
- **tooltip**, **tooltip-cursor**: on a touch screen, a tap shows the tooltip on something a tap does nothing else to (plain text, an icon, a chart's bar), and the next tap anywhere hides it. On a button or a link the tap is still the button's or link's own. The cursor tooltip shows beside the finger.
- **All widgets** (`base.css`): `tap-target` and `tap-target-y` utilities. On a touch screen they make a small control take taps from at least 44px around it without drawing it any bigger: both ways for a control on its own, only in height for one in a row, where growing sideways would take its neighbour's taps. Mice and trackpads keep the drawn size.
- **radio**: `variant="segmented"` lays the options out as one track with the chosen one raised in it, for two to four short choices, like the tabs' segmented look. It's still a radio group: the arrow keys move the choice, it submits with the form, and `wire:model`, `required`, disabled options and errors work as before. Without `variant`, radios look as they did.
- **field**: `visibleArea()` and `placeBeside()` exports: the part of the window not covered by an on-screen keyboard, and where a popover goes beside its field within it, its scrolling part cut down to fit when neither side has room for all of it.
- **color-picker**: a new form control, `<x-widget.color-picker>`: a hex box that submits `#rrggbb`, the browser's own colour picker beside it (`custom`), and optional `swatches` as a radio group named for screen readers. A preview shows the colour with black or white text, whichever reads better. `#ABC` tidies to `#aabbcc`, and text that isn't a colour goes back to the last one, saying why. Works with `wire:model`.
- **command**: a new widget, a command palette. `<x-widget.command>` with `<x-widget.command.group>`s and `<x-widget.command.item>`s: Ctrl+K (Cmd+K on a Mac), its button or any `data-command-open` element opens a search box over the page that finds pages and actions as you type, by label or keywords, every word in any order. Up and Down move through it while focus stays in the box; Enter or a click opens an item's `href`, or clicks it, so `wire:click` works. `suggest-url` adds your app's results with the same answer the search widget's suggestions take; `recent` keeps the last five pages chosen, in this browser only. `command-open` and `command-close` events from Livewire.
- **icon**: 40 more icons, drawn in the set's own style. App and admin: archive, bell-off, chart-bar, database, folder, key, layout-dashboard, log-in, printer, user-plus. Commerce and money: banknote, coins, gift, package, percent, receipt, shopping-bag, shopping-cart, store, truck. Editing and files: clipboard, file-text, folder-open, grip-vertical, list, paperclip, redo, save, scissors, undo. Communication and media: at-sign, camera, headphones, megaphone, message-circle, mic, music, rss, thumbs-up, video. Any other icon still comes from Blade Icons when the app has it.
- **popover**: a new widget, `<x-widget.popover>`: a panel of anything beside a button (a note, a short form, a profile card, links), as a non-modal dialog on the native popover, so a table or a modal never clips it. It flips above when there's no room below; focus moves into it as it opens and back to the button as it closes; Esc, a click outside, Tab past its end or a `data-popover-close` button closes it. The trigger is text, an icon or your own markup; `title`, `width` and `align` set the rest. `popover.open()`, `close()` and `toggle()` from scripts, and `popover-open` / `popover-close` events from Livewire.
- **slider**: a new form control, `<x-widget.slider>`. One thumb is the native range input, restyled, keeping its keyboard and screen reader support; `range` puts two thumbs on one track for a span such as a price range, each a `role="slider"` that stops at the other, moved by the arrow keys, Page Up and Down, Home and End, dragging, or a press on the track. `min`, `max` and `step`, and a `prefix` or `suffix` on the value shown under the label and read out per thumb, in the page's number format. Range values submit as `name[]` and bind with `wire:model` (`price.0`, `price.1`). Right to left fills from the right. `base.css` gets two `@source inline()` ranges for where the fill starts.
- **tags**: a new form control, `<x-widget.tags>`: free-text tags in a field. Enter or a comma adds what's typed, a pasted list adds each item, leaving the box keeps what's left in it, Backspace removes the last tag and each tag has its own remove button. `suggestions` offers the browser's own list, `max` caps how many, the same tag in another case isn't added twice, and a screen reader hears each change. Submits as `name[]` and works with `wire:model`.
- **tree**: a new widget, `<x-widget.tree>` with `<x-widget.tree.item>`s: nodes that open and close, as an ARIA tree. Four looks: `list` (compact rows under indent guides, like a file explorer), `cards` (each node a card joined to its children by lines, like a referral network), `org` (top-down, like an org chart) and `checkbox` (ticking a parent ticks everything under it, a dash when only some is; the ticked values submit as `name[]` and bind with `wire:model`). A node with `children-url` fetches its children from your app as HTML the first time it opens, with a spinner meanwhile and a retry if it fails. The arrow keys move through it, Right and Left following the reading direction; a letter jumps to the next node starting with it. Nodes take a label, `meta`, an `icon`, an `href` and a `details` slot. Cards and org charts scroll sideways inside themselves. Its lines and indent are in `base.css`, from CSS variables you can set on one tree's class: `--tree-line` (colour), `--tree-indent` and `--tree-gap`. Each node renders only its tree's look, about 1.1 KB of HTML.

### Changed

- **modal**, **alert**, **toast**, **datepicker**, **date-range-picker**: close buttons and the month arrows take taps from 44px around them on touch screens (`tap-target`). **password**, **number**, **captcha**, **file-upload**, **select** (clear), **pagination** (page numbers and arrows): the same, in height only (`tap-target-y`). Nothing looks different, and with a mouse nothing changes.
- **price-roll**: the change badge drops to the next line when a large price and its badge are wider than the screen, instead of running off a phone's edge.
- **pagination** (`jump`): the on-screen keyboard's return key reads Go.

### Fixed

- **modal**, **toast**, **pagination**: in a page drawn under the notch and home indicator (`viewport-fit=cover`, as in NativePHP and home-screen web apps), drawers, full-screen dialogs, bottom sheets and toasts keep their content and close buttons clear of them. Ordinary pages look exactly as before: the extra space is `env(safe-area-inset-*)`, which is 0 there.
- **search**, **select**, **phone**, **datepicker**, **date-range-picker**, **time-picker**: a list or calendar opened with the on-screen keyboard up no longer opens under it. It goes above the field when the keyboard leaves no room below, and moves back when the keyboard closes. Where neither side has room for the whole list (**search**, **select**, **phone**, **time-picker**'s list), it takes the roomier side and scrolls in what's left, instead of running under the keyboard or off the screen.

## [0.3.0] - 2026-10-06

### Changed

- **All widgets**: LarawellUI is now **Bladewell**. This one release breaks the promise above: the package, commands, config and lock file are renamed, with no aliases for the old names. The widgets, their props and `data-*` hooks, and the `App\View\Widget` / `App\Rules` code they copied into your app are unchanged. To upgrade:
    1. `composer remove --dev larawellui/larawellui && composer require --dev bladewell/bladewell`
    2. Rename `config/larawellui.php` to `config/bladewell.php`, if you published it.
    3. Rename `larawellui.lock` to `bladewell.lock` **before** running any command. Otherwise `bladewell:add --installed` can't tell which files you've edited and will overwrite them.
    4. Use `php artisan bladewell:add | list | diff | mcp` instead of `larawell:*`, and update the command in your MCP client's config.
    5. The site is now <https://www.bladewellui.com>.

## [0.2.3] - 2026-10-04

### Added

- **icon**, and with it every widget's icon props: a name that isn't in the built-in set is drawn by Blade Icons (`blade-ui-kit/blade-icons`) when the app has it, so `<x-widget.button icon-start="lucide-rocket">` or a dropdown item's `icon="lucide-zap"` work with any installed set, with the same classes and screen-reader handling. Built-in names still win, nothing new is required, and without Blade Icons an unknown name fails as before, its message now saying how to add one.

## [0.2.2] - 2026-10-04

### Added

- **All widgets** (`base.css`): a `data-theme-changing` hook for switching theme while the page is open. Set it on `<html>` for the switch and the widgets' colour transitions are held back, so fields, buttons and table rows change at once instead of each fading at its own speed. The README's Theming section shows the toggle.

### Changed

- **button** (`<x-widget.button.back>`): the arrow sits closer to its label (`gap-1.5`, as on the button, instead of `gap-5`), so the two read as one link.

### Fixed

- **table**: changing page, sort or filter in place no longer makes the browser report a Content Security Policy violation for each inline `<style>` on the page it fetches, such as the one Laravel's `@fonts` adds. Those carry the fetched response's nonce, not the current page's; the table now drops them before reading the page.
- **PHP helpers and validation rules** (copied to `App\View\Widget` and `App\Rules`): written in Laravel Pint's default style. Before, running Pint in your app reformatted seven of them, which marked them as edited, so `bladewell:add --installed` skipped their updates.

## [0.2.1] - 2026-10-04

### Added

- **field**: a `data-field-info` hook on the hint, which the field's script uses to announce the hint again once an error clears.

### Fixed

- **All form widgets**: a field showing an error is described by the error alone. Before, screen readers also read the hint the error hides, often two sentences saying nearly the same thing, like the birthday picker's "You must be 18 or older." after "You must be at least 18 years old." The hint is announced again once the error clears.
- **table**: the sortable example's demo data no longer logs a PHP deprecation ("Implicit conversion from float … to int") when it builds its amounts.

## [0.2.0] - 2026-10-03

### Added

- **show-if**: a new widget that shows part of a form only while another field has a given value, such as a phone number when Phone is chosen. Hidden, its fields don't submit and their required can't block the form; it starts in the right state after a failed submit, nests, and works inside Livewire.
- **breadcrumbs**: a new widget showing where a page sits, as a trail of links down to the current page (`aria-current="page"`). On phones, one link back up instead. Chevron or slash separators, an icon-only Home that keeps its name, and long labels cut short with the full text on hover.
- **search**: suggestions as you type, like a store's search, with `suggest-url`: your endpoint answers `GET ?q=…` with JSON. Suggestions can carry details, open a page directly (`href`) or sit under group headings; the typed text shows in bold. Keyboard and screen-reader support follow the ARIA combobox pattern, requests wait for a pause in typing and drop stale answers, and a failed one is announced. `suggest-min` and `messages` tune it.
- **select**: `search-url` searches your app as you type, for lists too long to send to the page (customers, products). Single or multiple: choices stay picked across searches, the chosen option(s) you pass show before any search, and other options you pass show as a starter list until you type. Searching and failures are shown and announced; `search-min-length`, `searching` and `search-failed` tune it. A Livewire `wire:model` multiple select takes values a search brought.
- **All widgets**: a dark theme in `theme.css`, at WCAG AA. Put `class="dark"` or `data-theme="dark"` on `<html>`, or on any element for just that part of the page.
- **Theme**: `primary-fill`, `error-fill` and `success-fill`, for solid backgrounds that carry white text. `primary`, `error` and `success` are now only for text, borders, focus rings and tints, so dark mode can make those lighter without failing contrast. In light mode each fill follows its base colour unless you set it, and `primary-hover` is now a darker mix of `primary-fill` in both modes, so a theme that changed only `primary` looks as it did, hover included. In dark mode the fills are a deeper shade of their own; if you change the brand colour, set the dark pair too.
- **icon**: 24 icons: arrow-down, arrow-up, bell, bookmark, circle-help, circle-x, credit-card, download, external-link, filter, globe, heart, home, link, mail, map-pin, menu, phone, send, settings, share, star, tag and users.
- **All widgets**: a manifest can name, in `examples-use`, other widgets its examples use without needing them itself. The page, `/r/{name}.json` (`examples_use`, `examples_install`), `bladewell:list --json` and MCP list them with the command that adds them.
- **All widgets**: every prop has a description on its component's page, in `/r/*.json` and through MCP. Props that take one of a set of values list them all, and the icon's `name` lists every icon.

### Changed

- **button, checkbox, accordion, date pickers, modal, pagination, progress, stepper, tabs, time-picker**: solid backgrounds under white text use the new fill colours, and focus rings leave a surface-coloured gap instead of a white one.
- **All widgets**: a value a prop doesn't take, like `variant="primay"` or `size="xl"`, now throws and names the values it does take. Before, the widget quietly used its default. If a value comes from your data (a status, a setting), map it to the widget's values with a default first; see "Values from your data" in the README. This covers accordion, alert, button, captcha, clock, dropdown, file-upload, modal, otp, pagination, price-roll, progress, search, select, stepper, switch, table, tabs, time-picker, toast and tooltip.
- **button**: `type` takes only button, submit or reset.
- **dropdown**: an item's `method` takes only GET, POST, PUT, PATCH or DELETE, in any case.
- **file-upload**: `uploaded` needs `upload-url`. Without it, a picked file was submitted under the same name as the stored ids and replaced them.
- **modal**: a drawer with `size="full"`, and `mobile="sheet"` on a drawer or a full-screen dialog, now throw. They used to be ignored.
- **table**: a row with both `href` and a details slot throws, because a click can only do one of the two. `bulk-method` takes only GET, POST, PUT, PATCH or DELETE.

### Fixed

- **field**: the required check for the select, date pickers and time picker skips fields inside a disabled fieldset, as the browser's own checks do. Before, a hidden required one could stop the form.
- **time-picker**: the list and each column now have one Tab stop, the chosen time or else the first that can be picked, which follows focus as the arrow keys move (a roving tabindex). Before, no option could be reached with Tab, and a scrolling list had no keyboard way in.
- **table**: an expandable row opens from a real Details button in its chevron cell, which carries `aria-expanded`. ARIA doesn't allow that on a table row, so screen readers could miss whether it was open. A click anywhere on the row still opens it.
- **tooltip**: around an icon with no text, the focusable wrapper is now an image named by the tooltip (`role="img"`). Before, it had a name but no role, which isn't allowed.
- **file-upload**: the button and image uploads no longer give their input two labels, which some screen readers read only half of. The input is named by the field label and the button's text, e.g. "Profile photo, Choose image".
- **progress, search**: the live-progress and search-form examples use `primary-fill` behind their white text, so they pass contrast in dark mode.
- **clock**: the countdown no longer gets an empty `aria-label` when its label is "".
- **icon, clock, pagination**: the icon's `name`, the clock's `hour12`, and the pagination components' `paginator` and `segmented` were missing from the docs, `/r/*.json` and MCP.
- **stepper**: the bars stepper takes the same step arrays as the other steppers (`['label' => …]`) instead of failing on them.

## [0.1.0] - 2026-10-02

### Added

- 34 widgets: accordion, alert, button, captcha, checkbox, clock, date-range-picker, datepicker, dropdown, field, file-upload, icon, modal, number, otp, pagination, password, phone, price-roll, progress, radio, search, select, stepper, switch, table, tabs, text-input, textarea, time-picker, toast, tooltip, tooltip-cursor and verification-code.
- `bladewell:add`, which copies widgets and everything they need into your app, with `--dry-run`, `--installed` and `--force`.
- `bladewell:list`, with `--json` for tools and AI agents.
- `bladewell:diff`, which shows how your edited copies differ from the package.
- `bladewell:mcp`, an MCP server for AI agents: browse the catalogue, see what's installed and edited, and install with a dry run first.
- `bladewell.lock`, so updates replace only the files you haven't edited.
- Livewire 3 and 4 support, with `wire:model` on every form control.

[Unreleased]: https://github.com/rkwebforge/bladewell/compare/v1.3.2...HEAD
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
