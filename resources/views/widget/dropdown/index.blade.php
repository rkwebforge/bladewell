@props([
    // The trigger button's id; scripts can open the menu by it, so it must be unique. Made up when left out.
    'id' => null,
    // Text for a button with a chevron, or an <x-slot:trigger> with your own markup (an avatar and name, say).
    // Without it, the trigger is an icon-only button.
    'trigger' => null,
    // The trigger's name for screen readers. Required for the icon-only trigger; the slot trigger uses it too.
    'label' => null,
    // The icon-only trigger's icon.
    'icon' => 'ellipsis',
    // The trigger's look, as on the button: primary, secondary, tertiary, danger, neutral or link.
    'variant' => 'neutral',
    // The trigger's size: sm, md or lg.
    'size' => 'md',
    // Which edge of the trigger the menu lines up with: start (left in left-to-right pages) or end.
    'align' => 'start',
    // The id of a shared menu (x-widget.dropdown.menu) rendered once elsewhere on the page. This dropdown is then only its trigger and
    // opens that menu, so a long table carries one copy of its row menu instead of one per row.
    'menu' => null,
    // With menu: this row's values, filled into the menu's :name placeholders as it opens, e.g. ['id' => $order->id].
    'values' => [],
])

@php
    // Scripts may open the menu by its id, so an explicit id must be unique; a derived one gets a suffix.
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'dropdown', explicit: $id !== null);
    $menuId = $menu ?? "{$id}-menu";
    // Which edge of the trigger the menu lines up with: start (left in left-to-right pages) or end.
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($align, ['start', 'end'], true)) {
        throw new \InvalidArgumentException("Unknown align [{$align}] for <x-widget.dropdown>. Use one of: start, end.");
    }
    // The trigger's look, passed to the button; checked here too, so the error names the tag that was written.
    if (! in_array($variant, ['primary', 'secondary', 'tertiary', 'danger', 'neutral', 'link'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.dropdown>. Use one of: primary, secondary, tertiary, danger, neutral, link.");
    }
    if (! in_array($size, ['sm', 'md', 'lg'], true)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.dropdown>. Use one of: sm, md, lg.");
    }
    if ($menu === null && $values !== []) {
        throw new \InvalidArgumentException('values on <x-widget.dropdown> fill a shared menu: add menu="…" with the id of an <x-widget.dropdown.menu>.');
    }
    if ($menu !== null && $slot->isNotEmpty()) {
        throw new \InvalidArgumentException("<x-widget.dropdown menu=\"{$menu}\"> opens a shared menu: put its items in <x-widget.dropdown.menu id=\"{$menu}\">, not here.");
    }
    // A placeholder is :name, so a key that isn't a name could never be filled in.
    foreach ($values as $key => $value) {
        if (! is_string($key) || preg_match('/^[A-Za-z_]\w*$/', $key) !== 1) {
            throw new \InvalidArgumentException("Value key [{$key}] on <x-widget.dropdown> can't be a placeholder: use letters, digits and _, starting with a letter.");
        }
        if (! is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException("Value [{$key}] on <x-widget.dropdown> must be a string, number, boolean or null.");
        }
    }

    // The trigger slot is your own markup (an avatar and name, say); a string is a button with a chevron;
    // neither is an icon-only button, which needs a label.
    $custom = $trigger instanceof \Illuminate\View\ComponentSlot;
    // Checked here, not left to the button, so the error names the tag that was written.
    if (! $custom && ! (is_string($trigger) && $trigger !== '') && ($label === null || trim($label) === '')) {
        throw new \InvalidArgumentException("<x-widget.dropdown> without a trigger is an icon-only button, so it needs a label, e.g. label=\"Actions for order #1042\": it is the button's only name for screen readers.");
    }
    $triggerAttributes = new \Illuminate\View\ComponentAttributeBag([
        'id' => $id,
        'popovertarget' => $menuId,
        'aria-haspopup' => 'menu',
        'aria-expanded' => 'false',
        'aria-controls' => $menuId,
        'data-dropdown-trigger' => '',
        // The script keeps aria-expanded in step with the menu; a Livewire render would put back the server's "false".
        'wire:ignore.self' => '',
    ]);
@endphp

{{--
    A menu button on the native popover: the browser opens it from popovertarget and closes it on Esc
    or a click outside, and it sits in the top layer, so a table's overflow or a modal can't clip it.
    resources/js/widget/dropdown adds the menu keyboard and positioning. data-no-row-click keeps a
    click in the menu from also opening a clickable table row.
--}}
{{-- With menu, the values sit here rather than on the trigger: the trigger keeps its own attributes through a Livewire render
     (wire:ignore.self), and values left behind from a re-ordered row would act on the wrong record. --}}
<div data-dropdown data-no-row-click data-align="{{ $align }}" @if ($menu !== null) data-dropdown-values="{{ json_encode((object) $values, JSON_THROW_ON_ERROR) }}" @endif {{ $attributes->class(['relative inline-flex']) }}>
    @if ($custom)
        {{-- It is one button, so keep links and other buttons out of the slot. --}}
        <button
            type="button"
            @if ($label) aria-label="{{ $label }}" @endif
            {{ $trigger->attributes->class(['focus-visible:ring-primary inline-flex items-center gap-2 rounded-xl text-start outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface'])->merge($triggerAttributes->getAttributes()) }}
        >{{ $trigger }}</button>
    @elseif (is_string($trigger) && $trigger !== '')
        <x-widget.button :variant="$variant" :size="$size" icon-end="chevron-down" :attributes="$triggerAttributes">{{ $trigger }}</x-widget.button>
    @else
        <x-widget.button :variant="$variant" :size="$size" :icon="$icon" :label="$label" :attributes="$triggerAttributes" />
    @endif

    @if ($menu === null)
        <div
            id="{{ $menuId }}"
            popover
            role="menu"
            {{-- The script positions the open menu (inline top/left, data-side); a Livewire render would wipe that and the
                 menu would jump. Its items still update. --}}
            wire:ignore.self
            aria-labelledby="{{ $id }}"
            data-dropdown-menu
            @class([
                // hidden until open: a display utility on a popover beats the browser's own rule that hides it while closed, and
                // the closed menu would sit there invisible, catching clicks meant for what's under it.
                'border-line bg-surface text-foreground fixed inset-auto m-0 hidden open:flex max-h-[min(24rem,calc(100dvh-2rem))] min-w-48 max-w-[calc(100vw-1rem)] flex-col overflow-y-auto overscroll-contain rounded-2xl border p-1.5 text-sm shadow-lg',
                // Fades and grows in from the trigger's side (data-side, set by the script when it flips above).
                'origin-top opacity-0 scale-95 transition-[opacity,scale,display,overlay] transition-discrete duration-150 open:opacity-100 open:scale-100 starting:open:opacity-0 starting:open:scale-95 data-[side=top]:origin-bottom motion-reduce:transition-none',
            ])
        >
            {{ $slot }}
        </div>
    @endif
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
