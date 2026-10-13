# Contributing to Bladewell

Thanks for helping. Here's where each kind of contribution goes.

## Bug reports and ideas

Open an [issue](https://github.com/rkwebforge/bladewell/issues/new/choose) with the bug report or feature request form. For a bug, the versions and the steps to see it matter most: most reports are fixed faster with them than with a description of the cause.

For a new widget or a bigger change, open a feature request before writing code, so you know whether it fits before you spend time on it.

## Security problems

Never in a public issue. Report them privately, as [SECURITY.md](SECURITY.md) explains.

## Pull requests

This repository is a **read-only copy**. Bladewell is developed in a private repository, together with its website, and each change is copied here; anything committed directly to this repository is replaced by the next copy. So a pull request can't be merged here as it is.

A pull request is still welcome, **as a proposal**. If it's accepted, the change is applied in the source repository, with you credited as a co-author of the commit and in the [changelog](CHANGELOG.md). It may be adjusted on the way, to the rules below, and you'll be told what changed.

Small, focused pull requests are the easiest to take: one fix, or one widget's change, with a test.

## What a change needs

Bladewell copies its widgets into apps that then own them, so a change has to keep working for everyone who copies it:

- **Nothing renamed or removed** within a major version: component names, props and their values, `data-*` hooks, JS exports, the `bladewell:*` commands and the config keys.
- **No framework JavaScript:** server-rendered Blade, plus a vanilla JS module hooked onto `data-*` attributes. No Alpine, no inline `<script>`, no `onclick=""`, no `style=""`.
- **Works inside Livewire 3 and 4** without needing it, including `wire:model` on form controls.
- **Accessible:** keyboard use, ARIA and focus handling, right-to-left layouts, and the app's locale for dates and numbers.
- **Output escaped** with `{{ }}`.
- **Tested:** the widget's behaviour, its error and empty cases, and its accessibility in light and dark.

"Working on the package" in the [README](README.md) explains how the widgets and the registry fit together.
