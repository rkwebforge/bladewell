{{-- variant="segmented": one track with the open tab raised in it, for two to four short choices. --}}
<x-widget.tabs id="billing" label="Billing period" variant="segmented" :tabs="['monthly' => 'Monthly', 'yearly' => 'Yearly']">
    <x-slot:monthly><p class="text-2xl font-semibold">$12 <span class="text-foreground/70 text-sm font-normal">a month</span></p></x-slot:monthly>
    <x-slot:yearly><p class="text-2xl font-semibold">$120 <span class="text-foreground/70 text-sm font-normal">a year, two months free</span></p></x-slot:yearly>
</x-widget.tabs>
