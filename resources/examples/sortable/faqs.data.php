<?php

declare(strict_types=1);

// Demo data for the preview only. A real app passes Faq::query()->orderBy('sort')->get().
return static fn (): array => [
    'faqs' => collect([
        ['id' => 1, 'question' => 'What is Bladewell?', 'answer' => 'Blade components you copy into your app and own from then on.'],
        ['id' => 2, 'question' => 'How long does shipping take?', 'answer' => 'Two to four working days within the EU, a week elsewhere.'],
        ['id' => 3, 'question' => 'Can I return an order?', 'answer' => 'Within 30 days, unused and in its box, at no cost to you.'],
        ['id' => 4, 'question' => 'Do you ship abroad?', 'answer' => 'To 40 countries; the checkout lists them.'],
        ['id' => 5, 'question' => 'How do I contact you?', 'answer' => 'By email or chat, Monday to Friday, 9 to 5.'],
    ])->map(fn (array $faq): object => (object) $faq),
];
