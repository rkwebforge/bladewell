{{-- Without move-url, a move only fires kanban:move, for your own script or a Livewire component to save. A locked card can't be moved, by the menu, the keys or a drag: here, a release that's already scheduled. --}}
<x-widget.kanban label="Sprint tasks">
    <x-widget.kanban.column id="todo" title="To do">
        <x-widget.kanban.card id="T-18" label="Write the release notes">Write the release notes</x-widget.kanban.card>
        <x-widget.kanban.card id="T-21" label="Check the date pickers in Safari">Check the date pickers in Safari</x-widget.kanban.card>
    </x-widget.kanban.column>
    <x-widget.kanban.column id="doing" title="Doing">
        <x-widget.kanban.card id="T-15" label="Tag version 2.0" locked>
            <p>Tag version 2.0</p>
            <p class="text-muted mt-1">Scheduled for Friday</p>
        </x-widget.kanban.card>
    </x-widget.kanban.column>
    <x-widget.kanban.column id="done" title="Done" empty="Nothing finished yet" />
</x-widget.kanban>
