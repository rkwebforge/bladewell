@props([
    // What the answer submits as. With a provider, the provider's own token field is used, and wire:model binds the token.
    'name' => 'captcha',
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // The image URL (e.g. route('captcha')); refreshing fetches it again, for a new code.
    'src' => null,
    // The URL of a spoken version: adds a play button.
    'audioSrc' => null,
    // inline (the image beside the field) or stacked (large, above it).
    'layout' => 'inline',
    // turnstile, recaptcha or hcaptcha instead of an image; check it with new Captcha($provider).
    'provider' => null,
    // The provider's public site key; defaults to config('services.{provider}.key').
    'siteKey' => null,
    // The provider widget's theme: auto, light or dark.
    'theme' => 'auto',
    // The provider widget's size: normal or compact.
    'size' => 'normal',
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // Shown while it's empty.
    'placeholder' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
])

@php
    // provider: Cloudflare Turnstile, Google reCAPTCHA or hCaptcha instead of an image. Their widget submits
    // its own token field, so errors are looked up under that name, or under the wire:model property the token is
    // copied to. Check it with new Captcha($provider).
    $service = $provider !== null ? (\Bladewell\Rules\Captcha::PROVIDERS[$provider] ?? throw new \InvalidArgumentException("Unknown captcha provider [{$provider}]. Use turnstile, recaptcha or hcaptcha.")) : null;
    $binding = $service !== null ? \Bladewell\Support\FormField::binding($attributes) : [];
    $field = \Bladewell\Support\FormField::make($service === null ? $name : ($binding === [] ? $service['field'] : null), $id, $errors ?? null, $error, $bag, 'captcha', attributes: $attributes);
    // The site key is public, but still comes from config (services.turnstile.key …) rather than the view.
    $siteKey ??= $provider !== null ? config("services.{$provider}.key") : null;
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($layout, ['inline', 'stacked'], true)) {
        throw new \InvalidArgumentException("Unknown layout [{$layout}] for <x-widget.captcha>. Use one of: inline, stacked.");
    }
    if (! in_array($theme, ['auto', 'light', 'dark'], true)) {
        throw new \InvalidArgumentException("Unknown theme [{$theme}] for <x-widget.captcha>. Use one of: auto, light, dark.");
    }
    if (! in_array($size, ['normal', 'compact'], true)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.captcha>. Use one of: normal, compact.");
    }
    $stacked = $layout === 'stacked';
    // In a Livewire update the image is left out (data-src only): the browser fetches an <img src> as soon as Livewire
    // turns the response into elements, and every fetch is a new code. The one on the page stays (wire:ignore);
    // resources/js/widget/captcha loads one that first appears in an update.
    $withSrc = ! (class_exists(\Livewire\Livewire::class) && \Livewire\Livewire::isLivewireRequest());
    $iconButton = 'text-foreground/70 hover:text-foreground focus-visible:ring-primary tap-target-y relative grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 disabled:opacity-50';
@endphp

@if ($service)
    <x-widget.field :required="$attributes->has('required')" :id="$field->id" :label="$label" :labels-control="false" :error="$field->errors" :info="$info" :disabled="$disabled" bare :class="$attributes->get('class')">
        {{-- The provider's script draws its widget here and adds the hidden token field to the form. Its own id is
             the token field's name (reCAPTCHA's textarea is id="g-recaptcha-response"), so this one must differ.
             wire:ignore: a Livewire render would empty it again, since the server sends it empty. --}}
        {{-- With wire:model, the provider's class is left off so its script doesn't draw the widget on its own:
             resources/js/widget/captcha draws it with render(), which hands back the token and an id to reset it by. --}}
        <div wire:ignore id="{{ $field->id }}-widget" role="group" @if ($label) aria-labelledby="{{ $field->id }}-label" @endif @if ($binding) data-captcha-provider="{{ $provider }}" data-captcha-script="{{ $service['script'] }}" @else class="{{ $service['class'] }}" @endif data-sitekey="{{ $siteKey }}" data-theme="{{ $theme }}" data-size="{{ $size }}" data-language="{{ str_replace('_', '-', app()->getLocale()) }}"></div>
        @if ($binding)
            {{-- The token, for wire:model. No name, so a plain form doesn't send it twice beside the provider's own field. --}}
            <input type="hidden" data-captcha-token {{ new \Illuminate\View\ComponentAttributeBag($binding) }}>
        @endif
        @if (! $siteKey)
            <p class="text-error mt-1 text-xs">Set services.{{ $provider }}.key in config/services.php to show the {{ $provider }} widget.</p>
        @endif
    </x-widget.field>

    @once
        <script src="{{ $service['script'] }}" async defer></script>
    @endonce
@else
    {{-- An image captcha: `src` must point at a route that generates the image and stores the answer in the session. --}}
    <x-widget.field :required="$attributes->has('required')" data-captcha :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :box="$stacked ? 'h-12 items-center' : 'h-12 items-center pe-3'" :class="$attributes->get('class')">
        @if ($stacked)
            <x-slot:before>
                {{-- Stacked: a large, readable image above the answer, with its controls beside it. --}}
                <div class="mb-2 flex items-center gap-2">
                    {{-- wire:ignore (here, on the small one and on the audio): a Livewire render would put the image's src back,
                         loading a new code and making the answer being typed wrong. Outside Livewire it does nothing. --}}
                    <div wire:ignore class="bg-foreground/5 relative h-16 w-full max-w-64 overflow-hidden rounded-xl">
                        @if ($src)
                            <img @if ($withSrc) src="{{ $src }}" @endif data-src="{{ $src }}" data-captcha-image alt="Captcha: type the characters shown" draggable="false" class="h-full w-full object-contain select-none">
                            {{-- Replaces a broken image (resources/js/widget/captcha). --}}
                            <span data-captcha-error hidden class="text-error absolute inset-0 flex items-center justify-center px-2 text-center text-xs leading-tight">Couldn't load the image. Try another.</span>
                        @else
                            <span class="text-muted flex h-full w-full items-center justify-center tracking-widest italic select-none">----</span>
                        @endif
                    </div>
                    @if ($src && $audioSrc)
                        <button type="button" data-captcha-play aria-label="Play the code aloud" aria-pressed="false" @disabled($disabled) class="{{ $iconButton }}"><x-widget.icon name="volume-2" class="size-5" /></button>
                    @endif
                    @if ($src)
                        <button type="button" data-captcha-refresh aria-label="Show another code" @disabled($disabled) class="{{ $iconButton }} aria-busy:*:animate-spin"><x-widget.icon name="refresh-cw" class="size-5" /></button>
                    @endif
                </div>
            </x-slot:before>
        @endif

        {{-- No old() refill: a failed submit shows a new image, so the previous answer is always wrong. --}}
        <input
            type="text"
            id="{{ $field->id }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            data-captcha-input
            @disabled($disabled)
            {{ $field->controlAttributes($attributes, (bool) $info)->class([
                'h-full w-full min-w-0 bg-transparent ps-5 outline-none placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50',
                'pe-5' => $stacked,
                'group-data-invalid/field:placeholder:text-error group-data-invalid/field:focus:placeholder:text-muted',
            ]) }}
        >

        @unless ($stacked)
            <div wire:ignore class="bg-foreground/5 relative ms-2 h-8 w-25 shrink-0 overflow-hidden rounded-sm">
                @if ($src)
                    <img @if ($withSrc) src="{{ $src }}" @endif data-src="{{ $src }}" data-captcha-image alt="Captcha: type the characters shown" draggable="false" class="h-full w-full object-contain select-none">
                    {{-- Replaces a broken image (resources/js/widget/captcha). --}}
                    <span data-captcha-error hidden class="text-error absolute inset-0 flex items-center justify-center px-2 text-center text-xs leading-tight">Couldn't load the image. Try another.</span>
                @else
                    <span class="text-muted flex h-full w-full items-center justify-center tracking-widest italic select-none">----</span>
                @endif
            </div>
            @if ($src && $audioSrc)
                <button type="button" data-captcha-play aria-label="Play the code aloud" aria-pressed="false" @disabled($disabled) class="{{ $iconButton }} ms-1"><x-widget.icon name="volume-2" class="size-5" /></button>
            @endif
            @if ($src)
                <button type="button" data-captcha-refresh aria-label="Show another code" @disabled($disabled) class="{{ $iconButton }} ms-1 aria-busy:*:animate-spin"><x-widget.icon name="refresh-cw" class="size-5" /></button>
            @endif
        @endunless

        @if ($src && $audioSrc)
            <audio wire:ignore data-captcha-audio data-src="{{ $audioSrc }}" @if ($withSrc) src="{{ $audioSrc }}" @endif preload="none"></audio>
        @endif
    </x-widget.field>
@endif
