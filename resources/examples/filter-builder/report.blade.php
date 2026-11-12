{{-- In any GET form: the filters go with the form as one field, for a report, an export or a saved search. A condition the server turned down comes back on its row with the reason, and only the ones it accepted are used. --}}
<form method="GET" class="grid max-w-3xl gap-6">
    <x-widget.filter-builder :state="$state" name="report_filters" label="Which customers" add-label="Add condition" />
    <div class="flex flex-wrap items-center gap-4">
        <x-widget.button type="submit">Show customers</x-widget.button>
        <p class="text-foreground/70 text-sm">{{ $matching }} of {{ $total }} customers match.</p>
    </div>
</form>
