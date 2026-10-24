{{-- Every icon in the set, by the name you pass. To add one, draw it in resources/views/widget/icon/index.blade.php. --}}
<ul class="grid grid-cols-[repeat(auto-fill,minmax(7.5rem,1fr))] gap-2">
    @foreach (['archive', 'arrow-down', 'arrow-left', 'arrow-right', 'arrow-up', 'at-sign', 'banknote', 'bell', 'bell-off', 'bookmark', 'calendar', 'calendar-days', 'calendar-range', 'camera', 'chart-bar', 'check', 'chevron-down', 'chevron-left', 'chevron-right', 'chevron-up', 'chevrons-left', 'chevrons-right', 'chevrons-up-down', 'circle-alert', 'circle-check', 'circle-help', 'circle-x', 'clipboard', 'clock', 'coins', 'copy', 'credit-card', 'database', 'download', 'ellipsis', 'external-link', 'eye', 'eye-off', 'file', 'file-text', 'filter', 'folder', 'folder-open', 'gift', 'globe', 'grip-vertical', 'headphones', 'heart', 'home', 'image', 'inbox', 'info', 'key', 'layout-dashboard', 'link', 'list', 'lock', 'log-in', 'log-out', 'mail', 'map-pin', 'megaphone', 'menu', 'message-circle', 'mic', 'minus', 'moon', 'music', 'package', 'panel-left', 'paperclip', 'pause', 'pencil', 'percent', 'phone', 'play', 'plus', 'printer', 'receipt', 'redo', 'refresh-cw', 'rotate-ccw', 'rss', 'save', 'scissors', 'search', 'send', 'settings', 'share', 'shield-check', 'shopping-bag', 'shopping-cart', 'star', 'store', 'sun', 'tag', 'thumbs-up', 'trash', 'triangle-alert', 'truck', 'undo', 'upload', 'user', 'user-plus', 'users', 'video', 'volume-2', 'wallet', 'x'] as $name)
        <li class="border-line text-foreground flex flex-col items-center gap-2 rounded-md border px-2 py-4">
            <x-widget.icon :name="$name" class="size-6" />
            <span class="text-muted font-mono text-xs">{{ $name }}</span>
        </li>
    @endforeach
</ul>
