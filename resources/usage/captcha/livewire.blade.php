{{-- An image captcha in a Livewire component ($captchaUrl from the component, for example captcha_src() with mews/captcha): bind the answer with wire:model; no name is needed, and its error is read under the property. The image (and the audio) stay put through every render, so the code being answered never changes under you; the refresh button still loads a new one. Validate it as usual in the component: 'captcha' => ['required', 'captcha']. Turnstile, reCAPTCHA and hCaptcha bind the same way: wire:model gets the provider's token when it's issued, and an empty one when it expires; check it with 'token' => ['required', new Captcha('turnstile')]. Send the form with wire:submit: a token is good for one check, so once a submit has sent it the provider gives a new one, after the render that comes back. --}}
<form wire:submit="send" class="space-y-5">
    <x-widget.captcha label="Type the characters" :src="$captchaUrl" wire:model="captcha" />

    {{-- Or, with a provider instead of the image: --}}
    <x-widget.captcha provider="turnstile" label="Security check" wire:model="token" />

    <button type="submit">Send</button>
</form>
