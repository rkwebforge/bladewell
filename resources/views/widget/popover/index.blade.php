@props([
    // The trigger button's id; scripts open the popover by it (popover.open('{id}')), so it must be unique. Made up when
    // left out.
    'id' => null,
    // Text for a button with a chevron, or a trigger slot with your own markup. Without either, the trigger is an
    // icon-only button.
    'trigger' => null,
    // The trigger's name for screen readers. Required for the icon-only trigger.
    'label' => null,
    // The icon-only trigger's icon.
    'icon' => 'info',
    // The trigger's look, as on the button: primary, secondary, tertiary, danger, neutral or link.
    'variant' => 'neutral',
    // The trigger's size: sm, md or lg.
    'size' => 'md',
    // A heading at the top of the panel, which also names it for screen readers. Without one, the trigger names it.
    'title' => null,
    // How wide the panel is: sm, md or lg.
    'width' => 'md',
    // Which edge of the trigger the panel lines up with: start (left in left-to-right pages), center or end.
    'align' => 'start',
])

@php
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($align, ['start', 'center', 'end'], true)) {
        throw new \InvalidArgumentException("Unknown align [{$align}] for <x-widget.popover>. Use one of: start, center, end.");
    }
    $widths = ['sm' => 'w-64', 'md' => 'w-80', 'lg' => 'w-96'];
    if (! array_key_exists($width, $widths)) {
        throw new \InvalidArgumentException("Unknown width [{$width}] for <x-widget.popover>. Use one of: sm, md, lg.");
    }
    // The trigger's look, passed to the button; checked here too, so the error names the tag that was written.
    if (! in_array($variant, ['primary', 'secondary', 'tertiary', 'danger', 'neutral', 'link'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.popover>. Use one of: primary, secondary, tertiary, danger, neutral, link.");
    }
    // Scripts open it by the trigger's id, so an explicit id must be unique; a derived one gets a suffix.
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'popover', explicit: $id !== null);
    $panelId = "{$id}-panel";
    $custom = $trigger instanceof \Illuminate\View\ComponentSlot;
    // Checked here, not left to the button, so the error names the tag that was written.
    if (! $custom && ! (is_string($trigger) && $trigger !== '') && ($label === null || trim($label) === '')) {
        throw new \InvalidArgumentException("<x-widget.popover> without a trigger is an icon-only button, so it needs a label, e.g. label=\"About this plan\": it is the button's only name for screen readers.");
    }
    $triggerAttributes = new \Illuminate\View\ComponentAttributeBag([
        'id' => $id,
        'popovertarget' => $panelId,
        'aria-haspopup' => 'dialog',
        'aria-expanded' => 'false',
        'aria-controls' => $panelId,
        'data-popover-trigger' => '',
        // The script keeps aria-expanded in step with the panel; a Livewire render would put back the server's "false".
        'wire:ignore.self' => '',
    ]);
@endphp

{{--
    A panel of anything (text, a form, links) beside a button, on the native popover: the browser opens it from
    popovertarget and closes it on Esc or a click outside, and it sits in the top layer, so a table's overflow or a modal
    can't clip it. resources/js/widget/popover places it, moves focus into it and back, and closes it when focus leaves.
    A non-modal dialog: the page stays usable behind it. For a menu of actions, see the dropdown.
--}}
<div data-popover data-no-row-click data-align="{{ $align }}" {{ $attributes->class(['relative inline-flex']) }}>
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

    <div
        id="{{ $panelId }}"
        popover
        role="dialog"
        tabindex="-1"
        {{-- The script places the open panel (inline top/left, data-side); a Livewire render would wipe that and it would
             jump. What's inside still updates. --}}
        wire:ignore.self
        @if ($title) aria-labelledby="{{ $panelId }}-title" @else aria-labelledby="{{ $id }}" @endif
        data-popover-panel
        @class([
            // hidden until open: a display utility on a popover beats the browser's own rule that hides it while closed.
            'border-line bg-surface text-foreground fixed inset-auto m-0 hidden max-h-[min(32rem,calc(100dvh-2rem))] max-w-[calc(100vw-1rem)] flex-col overflow-y-auto overscroll-contain rounded-2xl border p-4 text-sm shadow-lg outline-none open:flex',
            $widths[$width],
            // Fades and grows in from the trigger's side (data-side, set by the script when it flips above).
            'origin-top opacity-0 scale-95 transition-[opacity,scale,display,overlay] transition-discrete duration-150 open:opacity-100 open:scale-100 starting:open:opacity-0 starting:open:scale-95 data-[side=top]:origin-bottom motion-reduce:transition-none',
        ])
    >
        @if ($title)
            <h2 id="{{ $panelId }}-title" class="text-foreground mb-2 text-base font-semibold">{{ $title }}</h2>
        @endif
        {{ $slot }}
    </div>
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
