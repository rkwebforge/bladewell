@aware([
    'compact' => false,
])

@props([
    // Where the message is sent. Leave it off with wire:submit.
    'action' => null,
    // How it's sent: POST, or PUT, PATCH… (spoofed with _method, as Laravel does).
    'method' => 'POST',
    // What the text submits as. With wire:model on the composer, that names it instead.
    'name' => 'message',
    // The text box's name for screen readers.
    'label' => 'Message',
    // Shown in the box while it's empty.
    'placeholder' => 'Write a message…',
    // Text to start with. Old input wins after a failed submit.
    'value' => null,
    // The most characters it takes. The browser stops typing there; your Form Request must still check it (max:).
    'maxlength' => null,
    // Turns on the paperclip: files submit under this name as a list ("attachments" sends attachments[]).
    'attachments' => null,
    // The file types the paperclip offers, as for the accept attribute ("image/*,.pdf").
    'accept' => null,
    // Send without leaving the page: the message shows at once as Sending, then as the server sent it back (see the
    // sending note). Without the script it's a plain form; with wire:submit, Livewire sends instead.
    'async' => false,
    // The send button's name for screen readers.
    'sendLabel' => 'Send',
    // The text box's id. Defaults to one made for it.
    'id' => null,
])

@php
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'chat-message', explicit: $id !== null);
    $method = strtoupper($method);
    // wire:model binds the text box; everything else (wire:submit, class, data-*) goes on the form.
    $model = $attributes->whereStartsWith('wire:model');
    $files = $attachments ? preg_replace('/\[\]$/', '', $attachments) : null;
    // A failed send: the text's error, or else the files', read from the session as other fields do.
    $bag = $errors ?? null;
    $error = $bag instanceof \Illuminate\Support\ViewErrorBag
        ? ($bag->first($name) ?: ($files ? ($bag->first($files) ?: $bag->first($files.'.*')) : ''))
        : '';
    $round = 'focus-visible:ring-primary grid '.($compact ? 'size-8' : 'size-9').' shrink-0 place-items-center rounded-xl outline-none transition-colors focus-visible:ring-2 motion-reduce:transition-none';
@endphp

{{--
    A box that grows with what's typed, up to 12rem, then scrolls. Enter sends and Shift+Enter starts a new line (on a
    touch screen Enter is a new line, and the button sends, so the keyboard's Enter key keeps its usual name: no
    enterkeyhint="send"). Empty, with no files, it doesn't send, nor while a message is on its way (the button fades).

    With attachments, the paperclip picks files, each pick adding to the ones before, listed above the text as chips
    that can be taken off again, an image with a preview of it (a blob: URL, so a Content Security Policy's img-src must
    allow blob:). Files can also be pasted into the box or dropped on it, held to the same accept.
--}}
<form
    data-chat-composer
    @if ($async) data-chat-async @endif
    @if ($action) action="{{ $action }}" @endif
    method="{{ $method === 'GET' ? 'GET' : 'POST' }}"
    @if ($attachments) enctype="multipart/form-data" @endif
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['group/composer border-line bg-surface shrink-0 border-t', $compact ? 'p-2.5' : 'p-3 sm:p-4']) }}
>
    @if ($action && $method !== 'GET')
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif
    @endif

    {{-- Like the fields' frames: the border turns primary under the pointer and while anything in it has focus, no glow;
         and while a file is dragged over it, to show it can be dropped there. --}}
    <div class="border-line bg-surface hover:border-primary focus-within:border-primary group-data-dragging/composer:border-primary group-data-dragging/composer:bg-field mx-auto max-w-3xl rounded-2xl border shadow-sm transition-colors motion-reduce:transition-none">
        {{-- The chosen files, as chips the script draws. wire:ignore: a Livewire render would wipe them. --}}
        @if ($attachments)
            <ul data-chat-chips wire:ignore aria-label="Files to send" @class(['flex flex-wrap gap-2 empty:hidden', $compact ? 'px-2.5 pt-2.5' : 'px-3 pt-3'])></ul>
        @endif

        <label for="{{ $id }}" class="sr-only">{{ $label }}</label>
        {{-- field-sizing: content grows the box with its text where browsers have it; the script does it elsewhere. 16px
             (text-base) on phones too, though messages are smaller there: iOS zooms the page into a box with smaller text. Compact,
             14px from sm up, where that zoom doesn't happen. --}}
        <textarea
            id="{{ $id }}"
            data-chat-input
            @if ($model->isEmpty()) name="{{ $name }}" @endif
            {{ $model }}
            rows="1"
            placeholder="{{ $placeholder }}"
            @if ($maxlength) maxlength="{{ (int) $maxlength }}" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            class="placeholder:text-muted block max-h-48 w-full resize-none bg-transparent pb-1 text-base leading-6 outline-none [field-sizing:content] {{ $compact ? 'min-h-10 px-3 pt-2.5 sm:text-sm' : 'min-h-12 px-4 pt-3.5' }}"
        >{{ $model->isEmpty() ? old($name, $value) : $value }}</textarea>

        <div class="flex items-center gap-1 px-2 pb-2">
            @if ($attachments)
                {{-- The file input is the label's own, made invisible: a click on the paperclip opens the picker, and
                     Tab still reaches it, its focus ring drawn on the paperclip. --}}
                <label class="{{ $round }} text-foreground/70 hover:bg-field hover:text-foreground has-focus-visible:ring-primary relative cursor-pointer has-focus-visible:ring-2">
                    <x-widget.icon name="paperclip" class="size-5" />
                    <span class="sr-only">Attach files</span>
                    <input type="file" data-chat-files name="{{ $files }}[]" multiple @if ($accept) accept="{{ $accept }}" @endif class="sr-only">
                </label>
            @endif

            {{ $actions ?? '' }}

            <button type="submit" class="{{ $round }} bg-primary-fill text-on-primary hover:bg-primary-hover group-data-chat-sending/composer:cursor-progress group-data-chat-sending/composer:opacity-60 focus-visible:ring-offset-surface ms-auto focus-visible:ring-offset-2">
                <x-widget.icon name="arrow-up" class="size-5" />
                <span class="sr-only">{{ $sendLabel }}</span>
            </button>
        </div>
    </div>
    @if ($error)
        <p id="{{ $id }}-error" class="text-error mx-auto mt-2 max-w-3xl px-1 text-sm">{{ $error }}</p>
    @endif
</form>
