<?php

declare(strict_types=1);

// Demo data for the preview only: a conversation as an app would load it from its database. A message sent from the
// preview comes back for one page, with a reply saying it's a demo; nothing is kept.
return static function (): array {
    $at = now()->subMinutes(6);
    $messages = [
        (object) ['mine' => true, 'body' => 'Can you summarise last quarter from this report, and give me the query for refunds over $500?', 'sentAt' => $at, 'streaming' => false, 'files' => [['url' => '#q3-report', 'name' => 'Q3-report.pdf', 'size' => 2_516_582]]],
        (object) ['mine' => false, 'body' => "Here's Q3 at a glance:\n\n- **Revenue** grew 18% to \$1.42M, led by the Pro plan.\n- **Refunds** fell to 2.1% of orders, from 3.4%.\n- **New customers**: 1,284, the best quarter yet.\n\nFor refunds over \$500, in Laravel:\n\n```php\nRefund::where('amount', '>', 500)\n    ->latest()\n    ->get();\n```", 'sentAt' => $at->copy()->addMinute(), 'streaming' => false, 'files' => []],
        (object) ['mine' => true, 'body' => 'Which plan grew fastest?', 'sentAt' => $at->copy()->addMinutes(5), 'streaming' => false, 'files' => []],
        (object) ['mine' => false, 'body' => 'The **Pro** plan grew fastest, up 31% on Q2, mostly from teams moving up from Starter', 'sentAt' => null, 'streaming' => true, 'files' => []],
    ];

    $sent = session('chat-demo');
    if (($sent['example'] ?? null) === 'assistant') {
        $messages[] = (object) ['mine' => true, 'body' => (string) $sent['text'], 'sentAt' => now(), 'streaming' => false, 'files' => array_map(fn (array $file): array => ['url' => '#'.$file['name'], ...$file], $sent['files'])];
        $messages[] = (object) ['mine' => false, 'body' => 'Thanks for trying the demo! In your app, your controller saves the message and asks your model, and the reply streams in here.', 'sentAt' => now(), 'streaming' => false, 'files' => []];
    }

    return ['messages' => $messages];
};
