<?php

declare(strict_types=1);

namespace Bladewell;

use InvalidArgumentException;

/**
 * Something to weigh on the server when wiring a widget up: what the browser can't enforce for you, or what would
 * cost more than it needs to. Advice, not a rule: the app decides what applies to it.
 */
final readonly class Consideration
{
    /**
     * @param  array<string, string>  $links  title => URL, to the OWASP or Laravel page that explains it in full
     */
    public function __construct(
        public ConsiderationTopic $about,
        public string $note,
        public array $links = [],
    ) {}

    /**
     * @param  array<mixed>  $data  the decoded manifest
     * @return list<self>
     */
    public static function listFromManifest(string $name, array $data): array
    {
        $items = $data['consider'] ?? [];
        if (!is_array($items) || !array_is_list($items)) {
            throw new InvalidArgumentException("Widget [{$name}]: \"consider\" must be a list of {\"about\", \"note\", \"links\"}.");
        }

        return array_map(static function (mixed $item) use ($name): self {
            $about = is_array($item) && is_string($item['about'] ?? null) ? ConsiderationTopic::tryFrom($item['about']) : null;
            $note = is_array($item) ? ($item['note'] ?? null) : null;
            $links = is_array($item) ? ($item['links'] ?? []) : null;
            $validLinks = is_array($links) && ($links === [] || !array_is_list($links))
                && array_filter($links, static fn (mixed $url): bool => is_string($url) && str_starts_with($url, 'https://')) === $links;
            if ($about === null || !is_string($note) || $note === '' || !$validLinks) {
                throw new InvalidArgumentException("Widget [{$name}]: each \"consider\" entry needs about (security or cost), a note, and links as {\"title\": \"https://…\"}.");
            }

            /** @var array<string, string> $links */
            return new self($about, $note, $links);
        }, $items);
    }

    /**
     * @return array{about: string, note: string, links: list<array{title: string, url: string}>}
     */
    public function toArray(): array
    {
        return [
            'about' => $this->about->value,
            'note' => $this->note,
            'links' => array_map(static fn (string $title, string $url): array => ['title' => $title, 'url' => $url], array_keys($this->links), $this->links),
        ];
    }
}
