{{-- modal.confirm() asks a yes/no question with no markup and resolves true or false; the script beside this calls it. It starts on Cancel, so Enter can't confirm by accident; danger styles the confirm button for destructive actions. For a button that only needs to ask before it acts, confirm="…" on the button asks the same way with no script. --}}
<div class="flex flex-wrap items-center gap-3">
    <x-widget.button variant="danger" data-delete-wallet>Delete wallet</x-widget.button>
    <span id="confirm-result" class="text-foreground/75 text-sm" aria-live="polite"></span>
</div>
