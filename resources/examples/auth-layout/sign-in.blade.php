{{-- A sign-in page, with your brand's panel beside the form on a large screen and the form alone on a phone. It posts to the login route Laravel's starter kits name, so it works with Fortify or your own controller as it is. In your app, leave out the box around it: it fills the window. --}}
<div class="border-line h-[44rem] overflow-hidden rounded-2xl border">
    <x-widget.auth-layout title="Welcome back" subtitle="Sign in to your Acme account.">
        <x-slot:brand>
            <a href="{{ url('/') }}" class="focus-visible:ring-primary flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2">
                <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-8 place-items-center rounded-lg text-sm font-bold">A</span>
                <span class="font-semibold">Acme</span>
            </a>
        </x-slot:brand>

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
            @csrf
            <x-widget.text-input name="email" type="email" label="Email" autocomplete="username" required />
            <x-widget.password name="password" label="Password" required />
            <div class="flex items-center justify-between gap-4">
                <x-widget.checkbox name="remember" label="Remember me" />
                <a href="{{ route('password.request') }}" class="text-link hover:text-link-hover shrink-0 text-sm font-medium whitespace-nowrap underline-offset-4 hover:underline">Forgot password?</a>
            </div>
            <x-widget.button type="submit" class="mt-2 w-full">Sign in</x-widget.button>
        </form>

        <x-slot:footer>
            No account yet? <a href="{{ route('register') }}" class="text-link hover:text-link-hover font-medium underline-offset-4 hover:underline">Create one</a>
        </x-slot:footer>

        <x-slot:aside>
            <figure class="max-w-md">
                <blockquote class="text-xl leading-relaxed font-medium xl:text-2xl">“We moved our whole back office to Acme in a week. Our team hasn't looked back.”</blockquote>
                <figcaption class="mt-6 flex items-center gap-3 text-sm">
                    <span aria-hidden="true" class="bg-on-primary text-primary-fill grid size-10 place-items-center rounded-full font-semibold">MC</span>
                    <span>
                        <span class="block font-semibold">Maya Chen</span>
                        <span class="block">Head of Operations, Northwind</span>
                    </span>
                </figcaption>
            </figure>
        </x-slot:aside>
    </x-widget.auth-layout>
</div>
