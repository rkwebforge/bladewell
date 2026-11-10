{{-- Size and weight come from your own classes, so it fits a hero, a card or a sentence. The digits roll within the line they're given. --}}
<div class="grid gap-8">
    <x-widget.price-roll :value="1284930" currency="USD" locale="en-US" :decimals="0" label="Raised so far" class="text-4xl font-bold tracking-tight sm:text-6xl" />
    <div class="border-line bg-surface max-w-xs rounded-2xl border p-4">
        <p class="text-foreground/75 text-sm">Balance</p>
        <x-widget.price-roll :value="2408.17" currency="USD" locale="en-US" label="Balance" class="text-2xl font-semibold" />
    </div>
    <p class="text-foreground/75 max-w-prose">Your next invoice is <x-widget.price-roll :value="49" currency="USD" locale="en-US" label="Next invoice" class="text-foreground font-semibold" />, due on 1 October.</p>
</div>
