<?php

declare(strict_types=1);

namespace Bladewell;

enum ConsiderationTopic: string
{
    /** What the browser can't enforce: anyone can send your route any request, with or without the widget. */
    case Security = 'security';

    /** Requests, queries, storage or paid calls the widget can cause, and how to keep them down. */
    case Cost = 'cost';

    public function label(): string
    {
        return match ($this) {
            self::Security => 'Security',
            self::Cost => 'Server cost',
        };
    }
}
