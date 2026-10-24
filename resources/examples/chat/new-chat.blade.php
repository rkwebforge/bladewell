{{-- Before the first message: the empty slot, here a greeting and suggested prompts. A button with data-chat-prompt puts its text in the box, ready to send or change. The empty slot hides itself as soon as there's a message. --}}
<x-widget.chat label="Chat with Acme Assistant" class="border-line h-[34rem] overflow-hidden rounded-2xl border">
    <x-slot:empty>
        <div class="flex flex-col items-center text-center">
            <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-12 place-items-center rounded-2xl text-sm font-bold">AI</span>
            <h2 class="mt-5 text-2xl font-semibold tracking-tight">How can I help today?</h2>
            <p class="text-foreground/70 mt-2 text-sm">Ask about your orders, customers or reports.</p>
            <div class="mt-8 grid w-full gap-2 sm:grid-cols-2">
                @foreach (['Summarise this week\'s sales', 'Which customers haven\'t ordered in 90 days?', 'Draft a reply to a late-delivery complaint', 'Explain this month\'s refund rate'] as $prompt)
                    <button type="button" data-chat-prompt class="border-line hover:bg-field focus-visible:ring-primary rounded-xl border px-4 py-3 text-start text-sm outline-none transition-colors focus-visible:ring-2 motion-reduce:transition-none">{{ $prompt }}</button>
                @endforeach
            </div>
        </div>
    </x-slot:empty>

    <x-slot:composer>
        <x-widget.chat.composer async :action="route('chat.store')" placeholder="Ask anything…" maxlength="2000" />
    </x-slot:composer>
</x-widget.chat>
