{{-- variant="centered": the form on a card in the middle of a tinted page, for a short form or an app without a brand panel. It posts to password.email, the route Laravel's starter kits send the reset link from. --}}
<div class="border-line h-[34rem] overflow-hidden rounded-2xl border">
    <x-widget.auth-layout variant="centered" title="Forgot your password?" subtitle="Enter your email and we'll send you a link to choose a new one.">
        <x-slot:brand>
            <a href="{{ url('/') }}" class="focus-visible:ring-primary flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2">
                <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-8 place-items-center rounded-lg text-sm font-bold">A</span>
                <span class="font-semibold">Acme</span>
            </a>
        </x-slot:brand>

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf
            <x-widget.text-input name="email" type="email" label="Email" autocomplete="email" required />
            <x-widget.button type="submit" class="w-full">Email me a reset link</x-widget.button>
        </form>

        <x-slot:footer>
            Remembered it? <a href="{{ route('login') }}" class="text-link hover:text-link-hover font-medium underline-offset-4 hover:underline">Back to sign in</a>
        </x-slot:footer>
    </x-widget.auth-layout>
</div>
