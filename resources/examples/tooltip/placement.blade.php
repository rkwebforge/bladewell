{{-- placement is top (the default), bottom, start or end; start and end follow the page's direction. Without room on that side it moves to the opposite one. On a touch screen, a tooltip a finger opens goes on top instead, where the finger doesn't cover it. --}}
<div class="flex flex-wrap gap-3">
    @foreach (['top', 'bottom', 'start', 'end'] as $side)
        <x-widget.tooltip :text="'On the '.$side" :placement="$side">
            <button type="button" class="bg-field hover:bg-line focus-visible:ring-primary rounded-xl px-4 py-2.5 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface">{{ ucfirst($side) }}</button>
        </x-widget.tooltip>
    @endforeach
</div>
