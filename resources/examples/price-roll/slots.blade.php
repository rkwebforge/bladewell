{{-- The before and after slots hold your own markup around the rolling number: "from", "/month", a unit or an icon. They aren't rolled, and screen readers read them as written, around the price. Here the plan toggle rolls between the monthly and the yearly price. --}}
<div class="border-line bg-surface w-full max-w-sm rounded-3xl border p-5 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-3">
        <p class="text-base font-semibold sm:text-lg">Team plan</p>
        <div class="bg-field inline-flex rounded-full p-1 text-sm" role="group" aria-label="Billing period">
            <button type="button" class="aria-pressed:bg-surface aria-pressed:shadow-sm rounded-full px-3 py-1 font-medium" aria-pressed="true" data-billing="24" aria-controls="team-price">Monthly</button>
            <button type="button" class="aria-pressed:bg-surface aria-pressed:shadow-sm rounded-full px-3 py-1 font-medium" aria-pressed="false" data-billing="19" aria-controls="team-price">Yearly</button>
        </div>
    </div>
    <x-widget.price-roll id="team-price" :value="24" currency="USD" locale="en-US" :decimals="0" label="Team plan" trend-colors="none" live class="mt-4 text-5xl font-bold">
        <x-slot:before class="text-foreground/75 text-base font-medium">from</x-slot:before>
        <x-slot:after class="text-foreground/75 text-base font-medium">per seat / month</x-slot:after>
    </x-widget.price-roll>
    <p class="text-foreground/75 mt-2 text-sm">Yearly billing saves 20%.</p>
</div>
