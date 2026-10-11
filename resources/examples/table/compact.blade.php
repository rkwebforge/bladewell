{{-- density="compact" fits more rows; striped helps the eye along them; max-height keeps the header in view while the body scrolls. Badges carry each status in words, with colour only to back them up. The last column is a menu of actions per row: its heading is for screen readers only (hideLabel), and size="sm" keeps the button inside a compact row. The menu is rendered once after the table (dropdown.menu) and each row has only its trigger, whose values fill the menu's :user as it opens, so a long table carries one copy of the menu instead of one per row. --}}
<x-widget.table caption="Audit log" density="compact" striped max-height="20rem" :columns="['Time', 'User', 'Action', 'Result', ['label' => 'Actions', 'hideLabel' => true, 'align' => 'end']]" :rows="$events">
    @foreach ($events as $event)
        <x-widget.table.row>
            <td>{{ $event['time'] }}</td>
            <td>{{ $event['user'] }}</td>
            <td>{{ $event['action'] }}</td>
            <td><x-widget.table.badge :tone="$event['tone']">{{ $event['result'] }}</x-widget.table.badge></td>
            <td class="w-12 text-end">
                <x-widget.dropdown menu="audit-actions" :label="'Actions for '.$event['action'].' by '.$event['user'].' at '.$event['time']" size="sm" :values="['user' => $event['user']]" />
            </td>
        </x-widget.table.row>
    @endforeach
</x-widget.table>

<x-widget.dropdown.menu id="audit-actions" align="end">
    <x-widget.dropdown.item icon="eye">View details</x-widget.dropdown.item>
    <x-widget.dropdown.item icon="user">View :user</x-widget.dropdown.item>
    <x-widget.dropdown.divider />
    <x-widget.dropdown.item icon="shield-check">Mark as reviewed</x-widget.dropdown.item>
</x-widget.dropdown.menu>
