@props([
    // The page's heading, its h1 ("Welcome back").
    'title' => null,
    // A line under the heading ("Sign in to your account.").
    'subtitle' => null,
    // split: the form beside a panel in your brand's colour from lg up, the form alone below that. centered: the form
    // on a card in the middle of a tinted page.
    'variant' => 'split',
])

@php
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($variant, ['split', 'centered'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.auth-layout>. Use one of: split, centered.");
    }
    $split = $variant === 'split';
    // The form and its heading rise into place as the page first paints (@starting-style), unless motion is reduced.
    $enter = 'transition-[opacity,translate] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] starting:translate-y-3 starting:opacity-0 motion-reduce:transition-none';
@endphp

{{--
    A sign-in, sign-up or password page: your brand, a heading, the form (the slot) and a line under it, such as a link
    to the other page.

    split puts a panel in your brand's colour beside the form on large screens, with whatever the aside slot holds (a
    quote, a few words about the product) at its foot; on smaller screens the form has the page to itself. centered sets
    the form on a card in the middle of a tinted page.

    It fills the window (h-dvh), or its parent where that has a height of its own (max-h-full), like the examples here,
    and the form scrolls inside it when it's longer than the window. No script and no inline style.
--}}
<div
    data-auth-layout
    {{ $attributes->class([
        'text-foreground relative flex h-dvh max-h-full',
        'bg-surface' => $split,
        'flex-col overflow-y-auto bg-[color-mix(in_oklab,var(--color-field)_45%,var(--color-surface))]' => ! $split,
    ]) }}
>
    @if ($split)
        <main class="flex min-w-0 flex-1 flex-col overflow-y-auto px-6 py-6 sm:px-12">
            @isset($brand)
                <div class="flex shrink-0 items-center gap-3">{{ $brand }}</div>
            @endisset
            <div class="flex flex-1 items-center justify-center py-6">
                <div class="{{ $enter }} w-full max-w-sm">
                    @if ($title)
                        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
                    @endif
                    @if ($subtitle)
                        <p class="text-foreground/70 mt-2 text-sm">{{ $subtitle }}</p>
                    @endif
                    <div @class(['mt-8' => $title || $subtitle])>{{ $slot }}</div>
                    @isset($footer)
                        <div class="text-foreground/70 mt-8 text-center text-sm">{{ $footer }}</div>
                    @endisset
                </div>
            </div>
        </main>

        {{-- The brand panel, from lg up. Its glow sits in the top half, clear of the text at its foot, so that text is
             always on the plain fill: readable, and measurable by contrast checks. --}}
        <aside class="bg-primary-fill text-on-primary relative m-3 hidden w-1/2 shrink-0 flex-col justify-end overflow-hidden rounded-3xl transition-opacity duration-1000 starting:opacity-0 motion-reduce:transition-none lg:flex">
            <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-1/2">
                <div class="bg-on-primary/25 absolute -end-24 -top-28 size-96 rounded-full blur-3xl"></div>
                <div class="absolute start-1/4 -top-16 size-72 rounded-full bg-black/25 blur-3xl"></div>
                <div class="absolute inset-0 bg-[radial-gradient(color-mix(in_oklab,var(--color-on-primary)_30%,transparent)_1px,transparent_1px)] bg-size-[22px_22px] opacity-60"></div>
            </div>
            @isset($aside)
                <div class="relative p-10 xl:p-14">{{ $aside }}</div>
            @endisset
        </aside>
    @else
        <main class="flex min-h-full flex-col items-center justify-center gap-8 px-4 py-10">
            @isset($brand)
                <div class="flex items-center justify-center gap-3">{{ $brand }}</div>
            @endisset
            <div class="{{ $enter }} border-line bg-surface w-full max-w-md rounded-2xl border p-6 shadow-sm sm:p-8">
                @if ($title)
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
                @endif
                @if ($subtitle)
                    <p class="text-foreground/70 mt-2 text-sm">{{ $subtitle }}</p>
                @endif
                <div @class(['mt-6' => $title || $subtitle])>{{ $slot }}</div>
            </div>
            @isset($footer)
                <div class="text-foreground/70 text-center text-sm">{{ $footer }}</div>
            @endisset
        </main>
    @endif
</div>
