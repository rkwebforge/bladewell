@props([
    // assistant: replies run the full width, for an AI assistant; messages: bubbles on both sides, for people talking.
    'variant' => 'assistant',
    // Names the conversation for screen readers ("Chat with Acme Assistant").
    'label' => 'Conversation',
    // Where the messages before these come from, as HTML: a chat holding them, and its own history-url for the ones
    // before those (none when there are no more). Fetched as the top of the list scrolls into view. On this site only.
    'historyUrl' => null,
    // Smaller text and spacing, the size of a sidebar's, for a chat in a narrow column (sidebar-layout's panel, say).
    'compact' => false,
])

@php
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($variant, ['assistant', 'messages'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.chat>. Use one of: assistant, messages.");
    }
@endphp

{{--
    A conversation: messages (chat.message, with chat.divider and chat.typing between them), an optional empty slot for
    before the first one (suggested prompts, a greeting), and a composer slot for chat.composer. Give it a height, e.g.
    class="h-[36rem]" or one that fills the page.

    The list scrolls in a reversed column, so it paints at the newest message with no script, and messages added at the
    top (older ones) don't move what's being read. overflow-anchor: none, so the browser doesn't move it as well as the script. resources/js/widget/chat keeps the reader's place when one arrives at
    the bottom while they're scrolled up, and offers a button down to it instead. role="log": screen readers read out new
    messages as they arrive, without moving focus.
--}}
{{-- wire:ignore.self on the parts the script changes: where it's loading from, and the button down. Messages still
     update in a Livewire render. --}}
<section
    data-chat
    data-variant="{{ $variant }}"
    @if ($compact) data-chat-compact @endif
    wire:ignore.self
    @if ($historyUrl) data-chat-history="{{ $historyUrl }}" @endif
    {{ $attributes->class(['group/chat text-foreground bg-surface relative flex min-h-0 flex-col']) }}
>
    <div class="relative flex min-h-0 flex-1 flex-col">
        <div data-chat-scroll class="flex min-h-0 flex-1 flex-col-reverse overflow-y-auto overscroll-contain [overflow-anchor:none] [scrollbar-width:thin]">
            {{-- With no messages yet, what's here sits in the middle; with some, at the bottom, where chats start. --}}
            <div @class([
                'my-auto w-full group-has-[[data-chat-message]]/chat:my-0',
                'mx-auto max-w-3xl px-4 py-6 sm:px-6' => $variant === 'assistant' && ! $compact,
                'px-4 py-5 sm:px-5' => $variant === 'messages' && ! $compact,
                'px-3 py-4' => $compact,
            ])>
                @if ($historyUrl)
                    <div class="flex justify-center pb-5">
                        {{-- While it loads (data-busy, set by the script), a spinner turns beside the words, which say so too. --}}
                        <button type="button" data-chat-older wire:ignore class="group/older focus-visible:ring-primary text-foreground/70 hover:bg-field hover:text-foreground flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium outline-none focus-visible:ring-2 data-busy:cursor-progress"><span aria-hidden="true" class="hidden size-3.5 animate-spin rounded-full border-2 border-current border-e-transparent group-data-busy/older:inline-block motion-reduce:animate-[spin_1.5s_linear_infinite]"></span><span data-chat-older-label>Load older messages</span></button>
                    </div>
                @endif
                <div data-chat-log role="log" aria-label="{{ $label }}" @class(['flex flex-col', 'gap-8' => $variant === 'assistant' && ! $compact, 'gap-4' => $variant === 'messages' && ! $compact, 'gap-3' => $compact])>
                    {{ $slot }}
                </div>
                @isset($empty)
                    <div data-chat-empty class="group-has-[[data-chat-message]]/chat:hidden">{{ $empty }}</div>
                @endisset
            </div>
        </div>

        {{-- Shown by the script when a message arrives below while the reader is scrolled up. --}}
        <button
            type="button"
            data-chat-latest
            hidden
            wire:ignore.self
            class="bg-foreground text-surface focus-visible:ring-primary absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full py-1.5 ps-3 pe-2.5 text-xs font-medium shadow-lg outline-none transition-[opacity,translate] duration-200 focus-visible:ring-2 focus-visible:ring-offset-2 starting:translate-y-2 starting:opacity-0 motion-reduce:transition-none"
        >New messages <x-widget.icon name="arrow-down" class="size-3.5" /></button>
    </div>

    {{ $composer ?? '' }}

    @isset($composer)
        {{-- For a composer that sends without leaving the page (async): what the script copies to show a message the
             moment it's sent, drawn by the same components as the rest, so it looks the same in every variant and size.
             The server's own copy replaces it once it's in. --}}
        <template data-chat-outgoing>
            <x-widget.chat.message mine status="sending"><span data-chat-outgoing-text class="whitespace-pre-line"></span></x-widget.chat.message>
        </template>
        <template data-chat-outgoing-file>
            <x-widget.chat.attachment href="#" name="file" :size="1" />
        </template>
        <template data-chat-outgoing-image>
            <x-widget.chat.attachment href="#" name="image" preview="#" />
        </template>
        {{-- What a message that couldn't be sent says instead of Sending, with a way to try again. --}}
        <template data-chat-failed>
            <x-widget.icon name="circle-alert" class="size-3.5" /><span>Not sent</span>
            <button type="button" data-chat-retry class="focus-visible:ring-primary ms-1 rounded font-medium underline underline-offset-2 outline-none hover:no-underline focus-visible:ring-2">Retry</button>
        </template>
        {{-- What one the server turned away says, kept in the list because a new draft was started meanwhile: Edit puts it
             back into the box. --}}
        <template data-chat-rejected>
            <x-widget.icon name="circle-alert" class="size-3.5" /><span>Not sent</span>
            <button type="button" data-chat-edit class="focus-visible:ring-primary ms-1 rounded font-medium underline underline-offset-2 outline-none hover:no-underline focus-visible:ring-2">Edit</button>
        </template>
        <template data-chat-delivered>
            <x-widget.icon name="check" class="size-3.5" /><span>Sent</span>
        </template>
    @endisset
</section>
