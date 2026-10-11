@props([
    // Makes the item a link to this URL.
    'href' => null,
    // Makes the item a form that submits to this URL, with its CSRF token. Wins over href.
    'action' => null,
    // With action: the form's method, GET, POST, PUT, PATCH or DELETE, in any case.
    'method' => 'post',
    // An icon before the text.
    'icon' => null,
    // Red, for destructive actions such as delete; its confirm button is red too.
    'danger' => false,
    // Greyed out: it can't be chosen, and the arrow keys skip it.
    'disabled' => false,
    // A question, e.g. "Delete this order?": asked in a dialog first, and the item only acts once it's confirmed.
    'confirm' => null,
    // With confirm: more text under the question.
    'confirmMessage' => null,
    // With confirm: the confirm button's text. Defaults to the item's own text.
    'confirmLabel' => null,
    // With confirm: the cancel button's text. Defaults to "Cancel".
    'confirmCancel' => null,
    // In a shared menu (x-widget.dropdown.menu): the name of a value that shows the item only for rows where it is true, such as
    // 'can_delete'. It only hides the item: the controller must still authorize the request itself.
    'onlyIf' => null,
])

@php
    // A link (href), a form that submits (action + method, e.g. a delete), or a plain button for your own script.
    $kind = match (true) {
        $disabled => 'button',
        $action !== null => 'form',
        $href !== null => 'link',
        default => 'button',
    };
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array(strtoupper((string) $method), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        throw new \InvalidArgumentException("Unknown method [{$method}] for <x-widget.dropdown.item>. Use one of: GET, POST, PUT, PATCH, DELETE.");
    }
    $method = strtoupper($method);
    // Forms only speak GET and POST; anything else rides along as Laravel's _method field.
    $formMethod = $method === 'GET' ? 'GET' : 'POST';

    $classes = [
        'flex w-full shrink-0 cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start whitespace-nowrap outline-none select-none transition-colors',
        'aria-disabled:cursor-not-allowed aria-disabled:opacity-40',
        // The same text-on-tint mix as the table's error badge, so a danger item stays readable (AA).
        'text-[color-mix(in_oklab,var(--color-error)_80%,var(--color-foreground))] not-aria-disabled:hover:bg-error/10 focus-visible:bg-error/10' => $danger,
        'not-aria-disabled:hover:bg-field focus-visible:bg-field' => ! $danger,
    ];
    // Roving focus: the script moves focus between items with the arrow keys, so none sits in the Tab order.
    $itemAttributes = [
        'role' => 'menuitem',
        'tabindex' => '-1',
        'aria-disabled' => $disabled ? 'true' : null,
        'data-danger' => $danger ? '' : null,
        'data-confirm' => $confirm,
        'data-confirm-message' => $confirmMessage,
        'data-confirm-label' => $confirmLabel,
        'data-confirm-cancel' => $confirmCancel,
        'data-dropdown-only-if' => $onlyIf,
    ];
@endphp

@if ($kind === 'form')
    <form method="{{ $formMethod }}" action="{{ $action }}" class="contents">
        @if ($formMethod === 'POST')
            @csrf
        @endif
        @if (! in_array($method, ['GET', 'POST'], true))
            @method($method)
        @endif
        <button type="submit" {{ $attributes->class($classes)->merge($itemAttributes) }}>
@elseif ($kind === 'link')
    <a href="{{ $href }}" {{ $attributes->class($classes)->merge($itemAttributes) }}>
@else
    <button type="button" {{ $attributes->class($classes)->merge($itemAttributes) }}>
@endif
        @if ($icon)
            <x-widget.icon :name="$icon" @class(['size-4', 'opacity-60' => ! $danger]) />
        @endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
@if ($kind === 'link')
    </a>
@else
        </button>
    @if ($kind === 'form')
        </form>
    @endif
@endif
