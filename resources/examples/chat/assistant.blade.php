{{-- An AI assistant: your prompts in bubbles, its replies the full width of the conversation. markdown formats a reply with code in blocks, and treats it as untrusted, as what a model writes can be steered by what it reads: HTML in it is dropped, images stay as their description (a loaded image could carry the conversation away) and only safe links are links. A reply still being written shows a caret, and screen readers wait for it to finish. The paperclip attaches files, shown as chips before sending and as cards after. Enter sends; Shift+Enter starts a new line. --}}
<x-widget.chat label="Chat with Acme Assistant" class="border-line h-[40rem] overflow-hidden rounded-2xl border">
    @foreach ($messages as $message)
        <x-widget.chat.message :mine="$message->mine" :name="$message->mine ? null : 'Acme Assistant'" :initials="$message->mine ? null : 'AI'" :time="$message->sentAt" :streaming="$message->streaming" :markdown="$message->body">
            @if ($message->files !== [])
                <x-slot:attachments>
                    @foreach ($message->files as $file)
                        <x-widget.chat.attachment :href="$file['url']" :name="$file['name']" :size="$file['size']" />
                    @endforeach
                </x-slot:attachments>
            @endif
        </x-widget.chat.message>
    @endforeach

    <x-slot:composer>
        <x-widget.chat.composer async :action="route('chat.store')" placeholder="Ask anything…" maxlength="2000" attachments="attachments" accept="image/png,image/jpeg,image/webp,image/gif,.pdf,.csv" />
    </x-slot:composer>
</x-widget.chat>
