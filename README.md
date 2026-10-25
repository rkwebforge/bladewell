# Bladewell

`bladewell/bladewell`. **Try every widget live at [www.bladewellui.com](https://www.bladewellui.com)**: previews you can click through, in light and dark, with each one's props and source to copy. For AI agents it serves [`/llms.txt`](https://www.bladewellui.com/llms.txt) and `/r/{name}.json`.

Blade + Tailwind CSS v4 widgets for Laravel that you copy into your app, then own. No Livewire or Alpine needed: server-rendered Blade, plus a small vanilla JS file per widget that hooks onto `data-*` attributes. They work inside Livewire 3 and 4 components too (see below).

## Requirements

- PHP 8.3+, Laravel 12 or 13
- Tailwind CSS 4.1+ and Vite (the default Laravel setup), and pages rendered with Blade
- `datepicker`, `date-range-picker`, `time-picker` and `price-roll` need the PHP `intl` extension
- Browsers: Chrome/Edge 117+, Firefox 129+, Safari 17.5+ (all 2024). Chrome/Edge 114–116, Firefox 128 and Safari 17–17.4 work with some animations skipped. Older browsers can't open the popover-based widgets (select, date pickers, the time picker's list and columns, phone country list, dropdown, tooltips).
- On iOS/iPadOS before 18.3, tapping outside an open popover doesn't close it ([WebKit bug 267688](https://webkit.org/b/267688)).

Works under a strict Content Security Policy (`default-src 'self'`, no `'unsafe-inline'`): no widget renders inline scripts, event handlers or `style` attributes. The one opt-in exception is `remember` on the accordion menu: its small inline script carries your nonce if you set one with `Vite::useCspNonce()`, and where a CSP blocks it the menu still works, opening at the current page without remembering. Sizes the server works out are classes from the `@source inline()` ranges at the end of `base.css` (about 2 KB gzipped). Examples that call a widget's JS come with a `{slug}.js` for your own script. The captcha's provider options need that provider's domain in `script-src` and `frame-src`.

Not a fit for Tailwind v3 or Bootstrap projects, or for Inertia pages written in React or Vue. Components added to the page later (a Livewire render, `wire:navigate`, fetched fragments) set themselves up. The table, every input, the date pickers and the pagination work inside Livewire components. The table's page, sort and rows-per-page links update the component in place (see the table's Usage). Inputs bind with `wire:model` without a name, keep focus while you type, show the property's value after every render (a value set in PHP included) and read `$errors` under the property; the select takes options that change, and the captcha keeps its image. The file upload sends each file to Livewire's temporary uploads (`WithFileUploads`) as it's picked or dropped, keeps the property equal to its list as files are removed, and keeps its list and preview through renders; tested in a browser with Livewire 3.8 and 4.4. The accordion keeps panels open or shut as the person left them through a render, while what's inside updates; its `open` only sets how a panel starts (same browser test). Alerts update in place; a dismissed one stays hidden until its message changes, a flash set in an action is read out, and the error summary takes focus after a failed `wire:submit` (also browser-tested). A button submitting a `wire:submit` form shows its loading state while Livewire holds it and gets focus back after; `wire:loading.attr="aria-busy"` gives a `wire:click` button the same look (browser-tested). Dropdown items take `wire:click`, `confirm` and disabled items included, and an open menu stays open and in place through a render (browser-tested). The clocks keep ticking through renders, world clocks keep the visitor's offsets, and a running stopwatch or timer keeps running; its props are read once (browser-tested). An open modal stays open through renders, a component opens and closes one with `$this->dispatch('modal-open', id: '…')` and `'modal-close'`, and `reset-on-close` empties bound properties too (browser-tested). A component shows a toast with `$this->dispatch('toast', type: 'success', message: '…')`, and toasts keep working across `wire:navigate`, a flash before it included, shown once (browser-tested). Progress bars and steppers follow their properties through renders, `wire:poll` included; a bar driven by `progress.set()` goes in a `wire:ignore` (browser-tested). A price roll rolls to the value each render brings (browser-tested). Each widget's page shows it in a Livewire component under Usage.

## Versions and support

- **Within a major version, releases only add.** Component names, props and their values, `data-*` hooks, JS exports, the `bladewell:*` commands and the config keys are never renamed or removed in 1.x; a release can only get stricter about input that never worked, listed under **Changed** in the [changelog](CHANGELOG.md).
- **The newest 1.x release gets the fixes.** Bug and security fixes come as new 1.x releases, not as patches to older ones: update to the latest 1.x to get them.
- **When 2.0 is released, 1.x keeps getting security fixes for 12 months.**
- **Laravel and PHP:** Bladewell supports the Laravel versions Laravel itself still supports. A new Laravel version is added in a 1.x release once the test suite passes on it; dropping one that Laravel no longer supports waits for the next major version. The same goes for PHP.
- **Tailwind CSS:** 4.1 and later. A Tailwind release that changes the classes the widgets use would mean a new major version of Bladewell.
- **Security problems:** report them privately, as [SECURITY.md](SECURITY.md) explains.

## Install

```bash
composer require --dev bladewell/bladewell
php artisan bladewell:add datepicker        # adds datepicker plus field and icon, which it needs
npm run build
```

Starting a new app? The [starter kit](https://github.com/rkwebforge/bladewell-starter-kit) is a Laravel 13 app with Bladewell installed and nothing to remove: a home page, a light and dark theme, error pages and a strict Content Security Policy, with no accounts or database, and AI agents set up:

```bash
composer create-project bladewell/starter-kit my-app
```

With the Laravel installer, `laravel new my-app --using=bladewell/starter-kit` does the same.

> [!IMPORTANT]
> Don't `git clone` the starter kit to start an app. A clone keeps the starter kit's history and points `origin` at its repo, and skips setup: no `vendor/`, no `.env`, no app key. `create-project` gives you a clean copy of the latest release.

`bladewell:add` copies:

| What | Where |
| --- | --- |
| Blade components | `resources/views/components/widget/{widget}/` (used as `<x-widget.*>`) |
| JS | `resources/js/widget/{widget}/`, imported from `resources/js/app.js` |
| Theme tokens and base CSS | `resources/css/widget/{theme,base}.css`, imported from `resources/css/app.css` |
| PHP helpers (`FormField`, `ElementIds`, and `Countries` for the phone…) | `App\View\Widget` |
| Validation rules (the date pickers' `NotAfterToday` and `MinimumAge`, the captcha's `Captcha`) | `App\Rules` |

Before writing anything it checks `package.json`: it stops if Tailwind CSS is older than 4.1 (the installed version in `node_modules` if there is one, otherwise whether the declared range can reach 4.1), and warns if Tailwind or Vite isn't listed.

Run it again at any time, for example after `composer update`; `--installed` updates every widget already in the app. Files you haven't edited get the new version, files you have edited are skipped and reported, and `--force` overwrites those too, in the widgets you name. It never overwrites `theme.css`, `base.css` or a widget brought along for the ones you name; to take the package version of one of those, delete it and run `bladewell:add --installed`.

It tells the two apart with `bladewell.lock` in your app's root, a hash of each file as it was last installed. Commit it, like `composer.lock`. Files installed before the lock existed can't be told apart, so they are skipped and reported as well; if you haven't edited them, run once with `--force` and from then on updates apply by themselves.

```bash
php artisan bladewell:list            # what's available
php artisan bladewell:add             # pick from a list
php artisan bladewell:list --json     # components, requirements and usage examples, for tools and AI agents
php artisan bladewell:add --all
php artisan bladewell:add select --dry-run
php artisan bladewell:add --installed            # update every widget you have
php artisan bladewell:diff                       # diff every file a re-run would skip
php artisan bladewell:diff select/index.blade.php # or one file
```

`bladewell:diff` compares your copy with this version of the package. To take the package version of one file, delete it and run `bladewell:add --installed`.

To remove a widget, delete its folders and its import; with the default paths:

```bash
rm -r resources/views/components/widget/datepicker resources/js/widget/datepicker
# then delete `import './widget/datepicker';` from resources/js/app.js
```

`bladewell:add --installed` updates the widgets whose folder is there, so it won't bring a removed one back, unless a widget you kept still needs it (`field` and `icon` are shared by many), and then it should. PHP helpers and rules it brought, such as `Countries` for the phone, stay in `app/`; they do nothing unused, and you can delete them once nothing references them.

What you build on is only ever added to, never renamed or removed: component names, props and the values they take, `data-*` hooks, JS exports, the commands and their flags, and the config keys. A release can still get stricter about input that never worked, such as a mistyped value that used to be ignored and now throws. [CHANGELOG.md](CHANGELOG.md) lists every change by widget, so check the ones you use before updating.

## AI agents (MCP)

`php artisan bladewell:mcp` is a [Model Context Protocol](https://modelcontextprotocol.io) server for the app it runs in, over stdio. No extra package: it ships with this one. Its tools:

| Tool | What it does |
| --- | --- |
| `list_components` | The catalogue, with whether each is installed; `query` narrows it |
| `get_component` | One component's tags, props, slots, usage (Livewire included) and examples |
| `project_status` | What's installed, which installed files are out of date or edited (from `bladewell.lock`), and the Tailwind/Vite check |
| `add_components` | A dry run by default: lists the files it would write. Writes only with `confirm: true`, and never over edited files unless `force: true`, which covers the components named, not the theme or those brought along |

Add it to your agent. Claude Code, from the app's root:

```bash
claude mcp add bladewell -- php artisan bladewell:mcp
```

Other clients:

**Claude Desktop**: claude_desktop_config.json (Settings > Developer > Edit Config)

```json
{
    "mcpServers": {
        "bladewell": {
            "command": "php",
            "args": [
                "/path/to/your-app/artisan",
                "bladewell:mcp"
            ]
        }
    }
}
```

**Cursor**: .cursor/mcp.json

```json
{
    "mcpServers": {
        "bladewell": {
            "type": "stdio",
            "command": "php",
            "args": [
                "${workspaceFolder}/artisan",
                "bladewell:mcp"
            ]
        }
    }
}
```

**VS Code (Copilot)**: .vscode/mcp.json

```json
{
    "servers": {
        "bladewell": {
            "type": "stdio",
            "command": "php",
            "args": [
                "artisan",
                "bladewell:mcp"
            ],
            "cwd": "${workspaceFolder}"
        }
    }
}
```

**OpenAI Codex CLI**: Terminal

```bash
codex mcp add bladewell -- php /path/to/your-app/artisan bladewell:mcp
```

**Gemini CLI**: Terminal, from your app's root

```bash
gemini mcp add bladewell php /path/to/your-app/artisan bladewell:mcp
```

**Windsurf**: mcp_config.json (MCP settings > View raw config)

```json
{
    "mcpServers": {
        "bladewell": {
            "command": "php",
            "args": [
                "/path/to/your-app/artisan",
                "bladewell:mcp"
            ]
        }
    }
}
```

**Zed**: settings.json

```json
{
    "context_servers": {
        "bladewell": {
            "command": "php",
            "args": [
                "/path/to/your-app/artisan",
                "bladewell:mcp"
            ],
            "env": {}
        }
    }
}
```

**JetBrains AI Assistant**: Settings > Tools > AI Assistant > Model Context Protocol > Add, STDIO; Working directory: your app

```json
{
    "mcpServers": {
        "bladewell": {
            "command": "php",
            "args": [
                "artisan",
                "bladewell:mcp"
            ]
        }
    }
}
```

**JetBrains Junie**: .junie/mcp/mcp.json

```json
{
    "mcpServers": {
        "bladewell": {
            "command": "php",
            "args": [
                "/path/to/your-app/artisan",
                "bladewell:mcp"
            ]
        }
    }
}
```

Any other client: the command `php`, with the arguments `/path/to/your-app/artisan bladewell:mcp`. Where a client may not start the server in your app's folder, the full path to `artisan` is what makes it work. If a desktop app can't find `php`, give it the full path too (`which php`).

### Laravel Boost

The package ships guidelines for [Laravel Boost](https://github.com/laravel/boost) in `resources/boost/guidelines`: how to find and add widgets, and the rules an agent would otherwise get wrong (no Alpine or inline scripts, `readonly` rather than `disabled`, `confirm` on buttons, toasts, the shared row menu). Boost asks which packages' guidelines to include when you run `php artisan boost:install`, or `php artisan boost:update` after adding Bladewell: tick `bladewell/bladewell`, and they're written into your `CLAUDE.md`, `AGENTS.md` and the rest.

## Values from your data

A prop that takes one of a set of values (`variant`, `size`, `tone`…) throws on anything else, so a typo shows up in development instead of shipping the wrong look. A value that comes from your data, such as an order's status or a size from settings, can be one you didn't expect, and then the page fails. Map it to the widget's values first, with a default:

```blade
<x-widget.table.badge :tone="['paid' => 'success', 'failed' => 'error', 'pending' => 'warning'][$order->status] ?? 'neutral'">
    {{ $order->status }}
</x-widget.table.badge>
```

With an enum, a method on it (`$order->status->tone()`) keeps the mapping in one place, and a `match` without a default tells you when a new case needs one.

## Configuration

To install into other namespaces or paths, publish the config:

```bash
php artisan vendor:publish --tag=bladewell-config
```

Both namespaces must sit under a PSR-4 root in your `composer.json`. The installer derives the directory from it.

## Theming

Components only use the token names in `resources/css/widget/theme.css` (`primary`, `field`, `line`, `error`, …). To re-theme, edit the values there.

Each colour does one job. `primary` is for text, borders and focus rings; `primary-fill` is for solid backgrounds with `on-primary` text on them. `error-fill` and `success-fill` do the same for red and green. In light mode each fill follows its base colour unless you set it, and `primary-hover` is a darker mix of `primary-fill`, so changing `primary` alone restyles all three. On a dark page a shade light enough to read can't carry white text, so the dark set gives the fills a deeper shade of their own: change the brand colour there too.

Dark mode ships in the same file: put `class="dark"` or `data-theme="dark"` on `<html>` and every widget follows, popovers and dialogs included (on any other element, just that part of the page). To follow the device setting instead, swap its selector for `@media (prefers-color-scheme: dark) { :root { … } }`. Every pair meets WCAG AA in both modes.

Switching theme while the page is open: fields, buttons and table rows ease their colours on hover and focus, so a plain toggle makes each one fade to the new theme at its own speed. Set `data-theme-changing` on `<html>` for the switch and `base.css` holds those transitions back, so everything changes at once:

```js
const root = document.documentElement;
root.toggleAttribute('data-theme-changing', true);
root.classList.toggle('dark');
// Two frames: the new colours are painted before transitions come back.
requestAnimationFrame(() => requestAnimationFrame(() => root.removeAttribute('data-theme-changing')));
```

Icons: the icon widget draws its own set, which its `name` prop lists. For any other icon, install [Blade Icons](https://github.com/blade-ui-kit/blade-icons) and one of its sets; then every widget that takes an icon takes its names too, e.g. `<x-widget.button icon-start="lucide-rocket">`. Built-in names win when both have one.

## Working on the package

- The source runs as-is. Views live in `resources/views/widget/{widget}`, JS in `resources/js/{widget}`, CSS in `resources/css`, and the helpers and rules are real classes in `src/Support` and `src/Rules`. On install, `Bladewell\Support` and `Bladewell\Rules` are rewritten to the app's namespaces.
- `registry/{widget}.json` holds the metadata: an optional `title` (when the name doesn't read right as a heading, like `otp`) and `group` (the catalogue heading it's listed under, like `Forms`), `requires`, `support`, `rules`, `composer` and `php-extensions`, `examples-use` (other widgets its examples use that it doesn't need itself: the page names them with the command that adds them), plus `examples`, the display order of the files in `resources/examples/{widget}/`. A widget's files are whatever sits in its directories.
- Each example is a Blade file. A leading `{{-- … --}}` comment is its description, and the rest is the code. The site renders it as a live preview and shows the code, and `bladewell:list --json` and `/r/{name}.json` hand it to agents. Examples may only use their own widget, the widgets it requires and those its `examples-use` names, so copied code always works once those are added.
- An example that calls a widget's JS API keeps that call in `{slug}.js` beside it: markup with `data-*` hooks, and one `document.addEventListener` in the script, never `onclick=""` or an inline `<script>`. The site bundles these and shows them under the Blade. No example or widget may render `style=""`: a server-side size becomes a class, added to the `@source inline()` ranges in `base.css` if it's new. The test suite renders every example and fails on either.
- Props are read from each component's `@props` block, one prop per line.
- Every form control (the text input, password, select, the date pickers, the file upload…) sits in `<x-widget.field>` and requires `field`. Its script imports the shared helpers from `'../field'` (`on` for delegated events, `replaceValue`, `typingIn`, `onLivewireMorph`) rather than keeping a copy, and its own Livewire refresh goes through `onLivewireMorph`.
- The host app develops against the source directly. It registers `resources/views` as an anonymous component path (in `AppServiceProvider`) and imports the JS and CSS from `packages/bladewell/resources`. Edit a widget, refresh the page, done.
- The host app's test suite checks that every manifest declares everything its widget renders or imports, that every example renders, and that installing into a fresh project produces working, correctly namespaced files.
- Accessibility runs in a real browser: `npm run build`, then `composer test:a11y` (the first time, `npx playwright install chromium`). It runs axe over every example in the light and dark themes, against the WCAG 2.2 A and AA rules: as the page draws, then with each popover, dialog, toast and tooltip opened in turn, the way a person opens it. Anything axe can't decide fails too, unless the test settles it: it checks that a popup trigger's `aria-controls` target exists, that a list driven by `aria-activedescendant` really scrolls from the keyboard, and measures contrast itself where axe can't (SVG text, single characters). An opener that opens nothing fails too. CI runs it on every pull request.
