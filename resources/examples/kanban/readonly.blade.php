{{-- readonly shows the board as it is, for people who may look but not move anything: no move buttons, nothing to drag. --}}
<x-widget.kanban label="Hiring pipeline" readonly>
    <x-widget.kanban.column id="applied" title="Applied">
        <x-widget.kanban.card id="c-31" label="Noor Haddad">Noor Haddad · Designer</x-widget.kanban.card>
        <x-widget.kanban.card id="c-34" label="Tomás Silva">Tomás Silva · Engineer</x-widget.kanban.card>
    </x-widget.kanban.column>
    <x-widget.kanban.column id="interview" title="Interview">
        <x-widget.kanban.card id="c-27" label="Mei Chen">Mei Chen · Engineer</x-widget.kanban.card>
    </x-widget.kanban.column>
    <x-widget.kanban.column id="offer" title="Offer" empty="No offers out" />
</x-widget.kanban>
