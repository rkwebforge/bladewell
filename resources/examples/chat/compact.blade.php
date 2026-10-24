{{-- compact: the chat at a sidebar's size, 14px text with smaller avatars and tighter bubbles, for a narrow column such as sidebar-layout's panel or a help widget. Every part follows it: messages, files, the typing indicator and the composer, whose box stays 16px on phones so iOS doesn't zoom in. --}}
<x-widget.chat variant="messages" label="Chat with Acme Support" compact class="border-line mx-auto h-[32rem] max-w-xs overflow-hidden rounded-2xl border">
    <x-widget.chat.divider>Today</x-widget.chat.divider>
    <x-widget.chat.message name="Sam from Support" :time="today()->setTime(9, 12)">Hi! Anything I can help you with today?</x-widget.chat.message>
    <x-widget.chat.message mine :time="today()->setTime(9, 14)" status="read">Where do I change our billing email?</x-widget.chat.message>
    <x-widget.chat.message name="Sam from Support" :time="today()->setTime(9, 15)">
        Under Settings, then Billing. Here's a short guide:
        <x-slot:attachments>
            <x-widget.chat.attachment href="#billing-guide" name="billing-settings.pdf" :size="84_000" />
        </x-slot:attachments>
    </x-widget.chat.message>
    <x-widget.chat.typing name="Sam" />

    <x-slot:composer>
        <x-widget.chat.composer async :action="route('conversations.messages.store', 1)" maxlength="2000" attachments="attachments" accept="image/png,image/jpeg,image/webp,image/gif,.pdf" />
    </x-slot:composer>
</x-widget.chat>
