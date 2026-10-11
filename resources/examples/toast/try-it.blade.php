{{-- data-toast names the kind of toast and data-toast-message what it says: a click shows it, with no script. data-toast="dismiss" clears them all. A loading toast that turns into another needs your own script, like the one beside this. Needs <x-widget.toast /> once in your layout; see Usage. --}}
<div class="flex flex-wrap gap-2">
    <button type="button" class="bg-field rounded-full px-4 py-2 text-sm" data-toast="success" data-toast-message="Profile updated.">Success</button>
    <button type="button" class="bg-field rounded-full px-4 py-2 text-sm" data-toast="error" data-toast-message="Something went wrong.">Error</button>
    <button type="button" class="bg-field rounded-full px-4 py-2 text-sm" data-toast="warning" data-toast-message="Your session is about to expire.">Warning</button>
    <button type="button" class="bg-field rounded-full px-4 py-2 text-sm" data-notify="loading">Loading, then success</button>
    <button type="button" class="text-foreground rounded-full px-4 py-2 text-sm underline underline-offset-4" data-toast="dismiss">Dismiss all</button>
</div>
