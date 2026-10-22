{{-- Around something that can't take focus (an icon, a word), the tooltip makes it focusable, so keyboard users reach it too, and an icon with no text of its own is named by the tooltip. Keep it to text that adds to what's there; don't hide what someone needs in it. --}}
<p class="text-sm">
    Last synced 2 minutes ago
    <x-widget.tooltip text="Syncs every 5 minutes while the app is open" placement="end" class="align-middle">
        <x-widget.icon name="info" class="text-foreground/70 size-4" />
    </x-widget.tooltip>
</p>
