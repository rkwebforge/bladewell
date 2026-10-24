{{-- A sign-up page. The password field shows its rules as a checklist while it's typed; match them to your Form Request, e.g. Password::min(8)->numbers(). The terms box is required, so the browser asks for it before the form is sent. --}}
<div class="border-line h-[54rem] overflow-hidden rounded-2xl border">
    <x-widget.auth-layout title="Create your account" subtitle="Start your free 14-day trial. No card needed.">
        <x-slot:brand>
            <a href="{{ url('/') }}" class="focus-visible:ring-primary flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2">
                <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-8 place-items-center rounded-lg text-sm font-bold">A</span>
                <span class="font-semibold">Acme</span>
            </a>
        </x-slot:brand>

        <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
            @csrf
            <x-widget.text-input name="name" label="Name" autocomplete="name" required />
            <x-widget.text-input name="email" type="email" label="Email" autocomplete="email" required />
            <x-widget.password name="password" label="Password" new :min="8" numbers required />
            <x-widget.password name="password_confirmation" label="Confirm password" autocomplete="new-password" :toggle="false" required />
            <x-widget.checkbox name="terms" required>
                I agree to the <a href="#terms" class="text-link hover:text-link-hover underline underline-offset-4">terms</a> and <a href="#privacy" class="text-link hover:text-link-hover underline underline-offset-4">privacy policy</a>
            </x-widget.checkbox>
            <x-widget.button type="submit" class="mt-2 w-full">Create account</x-widget.button>
        </form>

        <x-slot:footer>
            Already have an account? <a href="{{ route('login') }}" class="text-link hover:text-link-hover font-medium underline-offset-4 hover:underline">Sign in</a>
        </x-slot:footer>

        <x-slot:aside>
            <p class="text-sm font-semibold tracking-wide uppercase">Everything in one place</p>
            <p class="mt-3 max-w-md text-2xl leading-snug font-semibold xl:text-3xl">Orders, customers and reports, ready the moment you sign up.</p>
            <ul class="mt-8 flex flex-col gap-3 text-sm">
                <li>Unlimited team members</li>
                <li>Exports to CSV and Excel</li>
                <li>Cancel any time</li>
            </ul>
        </x-slot:aside>
    </x-widget.auth-layout>
</div>
