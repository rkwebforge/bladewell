{{-- Inside a Livewire component, wire:submit sends and wire:model on the composer binds its text; a render that adds a message keeps the reader's place, and the empty slot hides by itself. For an AI reply, stream into a message marked streaming. Livewire streams only into an element already on the page, so send() adds the person's message and that streaming reply, then calls $this->js('$wire.respond()'), and respond() streams. Livewire puts streamed text in as HTML, so escape each piece: $this->stream(to: 'answer', content: e($chunk)). Once the reply is whole, give it to markdown, which formats it safely. Older messages: a wire:click button of your own at the top works as well as history-url, and the list doesn't jump as they go in. The paperclip sends files with a plain form; with Livewire, put a file input with wire:model in the composer's actions slot and take the files with WithFileUploads. --}}
<x-widget.chat label="Chat with Acme Assistant" class="h-[36rem]">
    @foreach ($messages as $message)
        <x-widget.chat.message wire:key="message-{{ $message->id }}" :mine="$message->mine" :name="$message->mine ? null : 'Acme Assistant'" :initials="$message->mine ? null : 'AI'" :markdown="$message->body" />
    @endforeach

    @if ($answering)
        <x-widget.chat.message name="Acme Assistant" initials="AI" streaming>
            <span wire:stream="answer">{{ $answer }}</span>
        </x-widget.chat.message>
    @endif

    <x-slot:composer>
        <x-widget.chat.composer wire:submit="send" wire:model="prompt" placeholder="Ask anything…" maxlength="2000" />
    </x-slot:composer>
</x-widget.chat>

{{-- In the component. Each reply is a paid model call, and every public method can be called, and every public
property set, from the browser: $answering only draws the page. So each action checks who's asking, and a reply is
claimed on the server, for one exact message, before the model is called. The messages table needs:
$table->boolean('awaiting_reply')->default(false);
public function send(): void
{
    Gate::authorize('reply', $this->conversation);
    $this->validate(['prompt' => ['required', 'string', 'max:2000']]);
    // Per person, not per tab: 20 messages a minute. Consider a daily cap too.
    if (! RateLimiter::attempt('chat:'.auth()->id(), 20, fn () => true)) {
        throw ValidationException::withMessages(['prompt' => 'You\'re sending quickly. Try again in a minute.']);
    }
    // awaiting_reply: this message may get one reply, which respond() claims by its id.
    $message = $this->conversation->messages()->create(['mine' => true, 'body' => $this->prompt, 'awaiting_reply' => true]);
    $this->messages[] = $message;
    $this->reset('prompt');
    $this->answering = true;
    $this->js("\$wire.respond({$message->id})");
}

public function respond(int $messageId): void
{
    Gate::authorize('reply', $this->conversation);
    // This message, in this conversation, only while it waits: claimed in one UPDATE. Of two requests at once only
    // one changes the row, so only one calls (and pays for) the model, and two prompts sent close together each get
    // their own reply. Called with an id that isn't waiting, it does nothing.
    $claimed = $this->conversation->messages()->whereKey($messageId)->where('mine', true)
        ->where('awaiting_reply', true)->update(['awaiting_reply' => false]) === 1;
    if (! $claimed) {
        $this->answering = false;

        return;
    }
    // Up to the message it answers, from the database: not newer ones, and not $this->messages, which the browser can change.
    $context = $this->conversation->messages()->where('id', '<=', $messageId)->latest('id')->limit(20)->get()->reverse()->values();
    $answer = '';
    try {
        foreach ($this->model->stream($context) as $chunk) {
            $answer .= $chunk;
            $this->stream(to: 'answer', content: e($chunk));
        }
    } catch (Throwable $e) {
        // Nothing was saved, so put the claim back: a Try again button can call respond() with the same id.
        $this->conversation->messages()->whereKey($messageId)->update(['awaiting_reply' => true]);
        $this->answering = false;
        report($e);
        $this->addError('prompt', 'The reply didn\'t come through. Try again.');

        return;
    }
    $this->messages[] = $this->conversation->messages()->create(['mine' => false, 'body' => $answer]);
    $this->answering = false;
}
--}}
