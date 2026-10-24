{{-- variant="messages": people talking, in bubbles on both sides, with names, avatars, and whether your messages were read. Older messages load from history-url as you scroll up, and a divider marks each day. Scrolled up when a message arrives, you stay where you are, with a button down to it. --}}
<x-widget.chat
    variant="messages"
    label="Conversation with Acme Support"
    :history-url="route('conversations.messages.index', ['conversation' => 1, 'page' => 1])"
    class="border-line h-[40rem] overflow-hidden rounded-2xl border"
>
    <x-widget.chat.divider>Today</x-widget.chat.divider>

    @foreach ($messages as $message)
        <x-widget.chat.message :mine="$message->mine" :name="$message->name" :time="$message->sentAt" :status="$message->status">
            {{ $message->body }}

            @if ($message->files !== [])
                <x-slot:attachments>
                    @foreach ($message->files as $file)
                        <x-widget.chat.attachment :href="$file['url']" :name="$file['name']" :size="$file['size']" :preview="$file['preview'] ?? null" />
                    @endforeach
                </x-slot:attachments>
            @endif
        </x-widget.chat.message>
    @endforeach

    <x-widget.chat.typing name="Sam" />

    <x-slot:composer>
        <x-widget.chat.composer async :action="route('conversations.messages.store', 1)" maxlength="2000" attachments="attachments" accept="image/png,image/jpeg,image/webp,image/gif,.pdf" />
    </x-slot:composer>
</x-widget.chat>
