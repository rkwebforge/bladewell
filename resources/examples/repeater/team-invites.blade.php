{{-- Invite several people at once, an email and a role a row. compact puts each row on one line with its buttons beside it; screen readers still hear "Remove Person 2". It starts with one blank row, so there's somewhere to type straight away. --}}
<form class="max-w-2xl">
    <x-widget.repeater name="invites" label="Invite your team" :min="1" :max="20" add-label="Add person" row-label="Person" compact>
        @foreach ((old() ? old('invites', []) : $invites) + ['__row__' => []] as $key => $invite)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="invites[{{ $key }}][email]" type="email" label="Email" placeholder="name@example.com" :value="$invite['email'] ?? null" required />
                <x-widget.select name="invites[{{ $key }}][role]" label="Role" :options="['member' => 'Member', 'admin' => 'Admin', 'viewer' => 'Viewer']" :value="$invite['role'] ?? 'member'" />
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>
</form>
