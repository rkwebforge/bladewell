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

{{-- In the component:
public function send(): void
{
    $this->validate(['prompt' => ['required', 'string', 'max:2000']]);
    $this->messages[] = $this->conversation->messages()->create(['mine' => true, 'body' => $this->prompt]);
    $this->reset('prompt');
    $this->answering = true;
    $this->js('$wire.respond()');
}

public function respond(): void
{
    $answer = '';
    foreach ($this->model->stream($this->messages) as $chunk) {
        $answer .= $chunk;
        $this->stream(to: 'answer', content: e($chunk));
    }
    $this->messages[] = $this->conversation->messages()->create(['mine' => false, 'body' => $answer]);
    $this->answering = false;
}
--}}
