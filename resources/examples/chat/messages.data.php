<?php

declare(strict_types=1);

// Demo data for the preview only: today's messages, as an app would load the latest from its database. Older days come
// from the demo history endpoint. A message sent from the preview comes back for one page; nothing is kept.
return static function (): array {
    $at = today()->setTime(9, 12);
    $sam = 'Sam from Support';
    $messages = [
        (object) ['mine' => true, 'name' => null, 'body' => 'Hi Sam! We just launched our new storefront. Here it is:', 'sentAt' => $at, 'status' => 'read', 'files' => [['url' => '#storefront', 'name' => 'new-storefront.png', 'size' => 184_320, 'preview' => asset('og-image.png')]]],
        (object) ['mine' => false, 'name' => $sam, 'body' => 'That looks fantastic, congratulations! The bold heading really works. How can I help today?', 'sentAt' => $at->copy()->addMinutes(3), 'status' => null, 'files' => []],
        (object) ['mine' => true, 'name' => null, 'body' => 'Could we add our designer to the account, so she can fine-tune the colours?', 'sentAt' => $at->copy()->addMinutes(4), 'status' => 'read', 'files' => []],
        (object) ['mine' => false, 'name' => $sam, 'body' => 'Of course: invite her under Settings, then Team, as an editor. Here is a short guide:', 'sentAt' => $at->copy()->addMinutes(6), 'status' => null, 'files' => [['url' => '#team-guide', 'name' => 'inviting-your-team.pdf', 'size' => 92_160]]],
        (object) ['mine' => true, 'name' => null, 'body' => "Done, she's in. Thanks, Sam!", 'sentAt' => $at->copy()->addMinutes(9), 'status' => 'sent', 'files' => []],
    ];

    $sent = session('chat-demo');
    if (($sent['example'] ?? null) === 'messages') {
        $messages[] = (object) ['mine' => true, 'name' => null, 'body' => (string) $sent['text'], 'sentAt' => now(), 'status' => 'sent', 'files' => array_map(fn (array $file): array => ['url' => '#'.$file['name'], ...$file], $sent['files'])];
        $messages[] = (object) ['mine' => false, 'name' => $sam, 'body' => 'Thanks for trying the demo! In your app, this message would be saved and reach us here.', 'sentAt' => now(), 'status' => null, 'files' => []];
    }

    return ['messages' => $messages];
};
