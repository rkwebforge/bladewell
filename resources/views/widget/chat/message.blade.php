@aware([
    'variant' => 'assistant',
    'compact' => false,
])

@props([
    // Sent by the person reading: on the end side, in a bubble.
    'mine' => false,
    // Who sent it ("Sam from Support"). Shown above their messages in the messages look, and read out before each one.
    'name' => null,
    // A picture of them, by URL. Without one, their initials (from name) or initials.
    'avatar' => null,
    // Up to two letters to show for them when there's no picture ("AI").
    'initials' => null,
    // When it was sent: a date, shown as the time in the app's locale (and, once the page is up, in the reader's own
    // timezone, not the server's), or text of your own ("Yesterday").
    'time' => null,
    // Your own message's state, in the messages look: sending, sent, read or failed.
    'status' => null,
    // Still being written, as an AI reply streams in: a caret after the text, and screen readers wait for the end.
    'streaming' => false,
    // The text as Markdown, from someone you can't fully trust (a model, a user): shown formatted, with any HTML in it
    // dropped, images left as their description and unsafe links unlinked (Bladewell\Support\ChatMarkdown). Instead of
    // the slot.
    'markdown' => null,
    // The text as plain text, shown as it was typed, line breaks kept, and escaped. Instead of the slot.
    'text' => null,
])

@php
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    $statuses = ['sending' => 'Sending', 'sent' => 'Sent', 'read' => 'Read', 'failed' => 'Not sent'];
    if ($status !== null && ! array_key_exists($status, $statuses)) {
        throw new \InvalidArgumentException("Unknown status [{$status}] for <x-widget.chat.message>. Use one of: ".implode(', ', array_keys($statuses)).'.');
    }
    $assistant = $variant === 'assistant';
    $words = $name ? preg_split('/\s+/', trim($name)) : [];
    // The first and last word's first letters: Sam from Support is SS, Maya Chen MC.
    $initials ??= $words === [] ? null : mb_strtoupper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : ''));
    $when = $time instanceof \DateTimeInterface
        ? \Illuminate\Support\Carbon::instance($time)->locale(app()->getLocale())->isoFormat('LT')
        : $time;
    $caret = "[&>:last-child]:after:bg-foreground/60 [&>:last-child]:after:ms-0.5 [&>:last-child]:after:inline-block [&>:last-child]:after:h-[1.1em] [&>:last-child]:after:w-[0.45em] [&>:last-child]:after:translate-y-[0.15em] [&>:last-child]:after:animate-pulse [&>:last-child]:after:rounded-[2px] [&>:last-child]:after:content-[''] motion-reduce:[&>:last-child]:after:animate-none "
        ."[&:not(:has(>*))]:after:bg-foreground/60 [&:not(:has(>*))]:after:ms-0.5 [&:not(:has(>*))]:after:inline-block [&:not(:has(>*))]:after:h-[1.1em] [&:not(:has(>*))]:after:w-[0.45em] [&:not(:has(>*))]:after:translate-y-[0.15em] [&:not(:has(>*))]:after:animate-pulse [&:not(:has(>*))]:after:rounded-[2px] [&:not(:has(>*))]:after:content-[''] motion-reduce:[&:not(:has(>*))]:after:animate-none";
    // The person's own message has no avatar; theirs does, when there's anything to show.
    $showAvatar = ! $mine && ($avatar || $initials);
    // Formatted text (paragraphs, lists, code) from your Markdown renderer, styled here so it reads well in either look.
    // Long code lines wrap rather than scroll: a scrolling block from Markdown can't be reached from the keyboard.
    $prose = '[&_a]:text-link [&_a]:underline [&_a]:underline-offset-2 [&_strong]:font-semibold [&_p+*]:mt-3 [&_ul+*]:mt-3 [&_ol+*]:mt-3 [&_pre+*]:mt-3 [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:list-decimal [&_ol]:ps-5 [&_li+li]:mt-1 [&_pre]:bg-field [&_pre]:text-foreground [&_pre]:border-line [&_pre]:border [&_pre]:break-words [&_pre]:whitespace-pre-wrap [&_pre]:rounded-xl [&_pre]:p-4 [&_pre]:text-xs [&_pre]:leading-relaxed [&_code]:font-mono [&_:not(pre)>code]:bg-field [&_:not(pre)>code]:rounded-md [&_:not(pre)>code]:px-1.5 [&_:not(pre)>code]:py-0.5 [&_:not(pre)>code]:text-[0.875em]';
@endphp

{{-- aria-busy while streaming: screen readers read the reply out once it's whole, not word by word. --}}
<div
    data-chat-message
    @if ($mine) data-mine @endif
    @if ($streaming) aria-busy="true" @endif
    {{ $attributes->class([
        'flex transition-[opacity,translate] duration-300 ease-out starting:translate-y-1.5 starting:opacity-0 motion-reduce:transition-none',
        'flex-row-reverse' => $mine,
        'gap-4' => $assistant && ! $mine && ! $compact,
        'gap-3' => (! $assistant || $mine) && ! $compact,
        'gap-2' => $compact,
    ]) }}
>
    @if ($showAvatar)
        @if ($avatar)
            <img src="{{ $avatar }}" alt="" @class(['bg-field shrink-0 rounded-full object-cover', $compact ? 'size-7' : 'size-8'])>
        @else
            <span aria-hidden="true" @class([
                'grid shrink-0 place-items-center rounded-full font-semibold',
                'size-8 text-xs' => ! $compact,
                'size-7 text-[0.6875rem]' => $compact,
                'bg-primary-fill text-on-primary' => $assistant,
                'bg-field text-foreground' => ! $assistant,
            ])>{{ $initials }}</span>
        @endif
    @endif

    <div @class([
        'flex min-w-0 flex-col',
        'items-end' => $mine,
        'flex-1' => $assistant && ! $mine,
        'max-w-[85%]' => $mine || ! $assistant,
    ])>
        @if (! $mine && $name && ! $assistant)
            <p class="text-foreground/70 mb-1 text-xs font-medium">{{ $name }}</p>
        @endif

        {{-- Who said it, for screen readers, as each message is read out on its own. --}}
        <span class="sr-only">{{ $mine ? 'You' : ($name ?? 'Reply') }}:</span>

        @if ($markdown !== null || $text !== null || $slot->isNotEmpty())
            <div @class([
                // A step smaller on phones, where a bubble is narrow; from sm up, the size of the page's own text.
                'min-w-0 break-words text-sm',
                'sm:text-base sm:[&_pre]:text-sm' => ! $compact,
                $prose,
                // Their reply in the assistant look: no bubble, the width of the conversation.
                'pt-1 leading-6 sm:leading-7' => $assistant && ! $mine && ! $compact,
                'pt-0.5 leading-6' => $assistant && ! $mine && $compact,
                // Still being written: a caret at the end of the text, on its last paragraph when it has some.
                $caret => $streaming,
                'rounded-2xl px-4 py-2.5 leading-relaxed' => ($mine || ! $assistant) && ! $compact,
                'rounded-2xl px-3 py-2 leading-normal' => ($mine || ! $assistant) && $compact,
                'bg-field rounded-ee-md' => $mine && $assistant,
                'bg-primary-fill text-on-primary rounded-ee-md [&_a]:text-on-primary [&_:not(pre)>code]:bg-on-primary/15' => $mine && ! $assistant,
                'bg-field rounded-ss-md' => ! $mine && ! $assistant,
            ])>@if ($markdown !== null){{-- Unescaped on purpose: ChatMarkdown built this HTML itself, from escaped text, with no HTML of the message's own. --}}{!! \Bladewell\Support\ChatMarkdown::toHtml($markdown) !!}@elseif ($text !== null)<span class="whitespace-pre-line">{{ $text }}</span>@else{{ $slot }}@endif</div>
        @endif

        @isset($attachments)
            <div @class(['mt-2 flex flex-wrap gap-2', 'justify-end' => $mine])>{{ $attachments }}</div>
        @endisset

        @if ($when || ($mine && $status))
            <p data-chat-meta @class(['text-foreground/70 mt-1 flex items-center gap-1 text-xs', 'text-error' => $status === 'failed'])>
                @if ($time instanceof \DateTimeInterface)
                    <time data-chat-time datetime="{{ $time->format(DATE_ATOM) }}">{{ $when }}</time>
                @elseif ($when)
                    <span>{{ $when }}</span>
                @endif
                @if ($mine && $status)
                    @if ($when)
                        <span aria-hidden="true">·</span>
                    @endif
                    {{-- data-chat-status: the script turns a message being sent into one that wasn't, with a way to retry. --}}
                    <span data-chat-status class="inline-flex items-center gap-1">
                        @if ($status === 'sending')
                            <x-widget.icon name="clock" class="size-3.5" />
                        @elseif ($status === 'failed')
                            <x-widget.icon name="circle-alert" class="size-3.5" />
                        @else
                            <x-widget.icon name="check" :class="$status === 'read' ? 'text-primary size-3.5' : 'size-3.5'" />
                        @endif
                        <span>{{ $statuses[$status] }}</span>
                    </span>
                @endif
            </p>
        @endif
    </div>
</div>
