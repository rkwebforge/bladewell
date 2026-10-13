# Security policy

## Reporting a vulnerability

Please report security problems privately, not in a public issue, so they can be fixed before anyone else knows about them.

Report one through GitHub's private vulnerability reporting: [open a report](https://github.com/rkwebforge/bladewell/security/advisories/new) (the repository's **Security** tab, then **Report a vulnerability**). Only the maintainer sees it.

Include what you can of:

- the widget or command affected, and the Bladewell version (`composer show bladewell/bladewell`);
- what an attacker could do, and what they need to do it;
- the steps or markup that show it, with the Laravel, PHP and browser versions.

You'll get a reply within 5 working days, saying whether it's confirmed and what happens next. Once a fix is released, the report is published as a GitHub security advisory, crediting you unless you'd rather not be named.

## Supported versions

| Version | Security fixes |
| --- | --- |
| 1.x | Yes: fixed in a new 1.x release |
| 0.x, LarawellUI | No: move to 1.x (see the [changelog](CHANGELOG.md)) |

When 2.0 is released, 1.x keeps getting security fixes for 12 months.

## Getting a fix into your app

Bladewell copies its widgets into your app, so a new release changes nothing until you take it:

```bash
composer update bladewell/bladewell
php artisan bladewell:add --installed   # updates the widget files you haven't edited
php artisan bladewell:diff              # shows what a fix changes in the ones you have
```

A widget file you've edited isn't overwritten: `bladewell:diff` shows the fix, for you to apply by hand. Security fixes are listed under **Security** in the [changelog](CHANGELOG.md), with the widgets they affect.
