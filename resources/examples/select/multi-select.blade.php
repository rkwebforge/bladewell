{{-- multiple submits networks[] and keeps the list open while you pick. display sets how the choices show: chips (each removable; Backspace removes the last), count ("3 selected", full list in the tooltip) or list (joined, the default). clearable adds a button to empty it. Listen for the select:change event to react in JS. --}}
@php
    $networks = [
        'trc20' => 'TRC20 (Tron)',
        'erc20' => 'ERC20 (Ethereum)',
        'bep20' => 'BEP20 (BNB Chain)',
        'sol' => 'Solana',
        'arb' => 'Arbitrum',
    ];
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <x-widget.select name="networks" label="Networks" placeholder="Choose networks" multiple clearable display="chips" :value="['trc20', 'erc20', 'bep20']" :options="$networks" class="sm:col-span-2" />
    <x-widget.select name="alert_networks" label="Alert me on" placeholder="Any network" multiple display="count" count-label=":count networks" :value="['trc20', 'sol', 'arb']" :options="$networks" />
    <x-widget.select name="payout_networks" label="Payout networks" placeholder="Choose networks" multiple :value="['trc20', 'bep20']" :options="$networks" />
</div>
