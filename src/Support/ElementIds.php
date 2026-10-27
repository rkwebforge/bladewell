<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Illuminate\Container\Attributes\Scoped;
use LogicException;
use WeakReference;

/**
 * Keeps element ids unique within one response, so labels, aria-describedby and #fragments
 * always point at the right element. Scoped: a fresh set per request (and per Octane/queue cycle).
 */
#[Scoped]
final class ElementIds
{
    /** @var array<string, string|null> id => the Livewire component that claimed it, null for the page around them */
    private array $used = [];

    /** @var array<string, WeakReference<object>> Livewire component id => the instance whose render claimed its ids */
    private array $renders = [];

    /**
     * Reserves an id for this response.
     *
     * A derived id (built from a field name) gets a -2, -3 … suffix when already taken. An explicit
     * id is one the caller chose and may reference from JS or CSS, so silently renaming it would
     * break that reference; a duplicate throws instead, which surfaces in development and tests.
     */
    public function claim(string $id, bool $explicit = false): string
    {
        $owner = $this->owner();
        if (! array_key_exists($id, $this->used)) {
            return $this->reserve($id, $owner);
        }

        if ($explicit) {
            throw new LogicException("Duplicate element id [{$id}] on this page. Give one of the widgets a different id or name.");
        }

        $suffix = 2;
        while (array_key_exists("{$id}-{$suffix}", $this->used)) {
            $suffix++;
        }

        return $this->reserve("{$id}-{$suffix}", $owner);
    }

    /**
     * The Livewire component rendering now (Livewire shares it with every view as $__livewire), or null outside one.
     *
     * A component rendering again in the same request, as Livewire::test() does when an action is called after it
     * mounted, replaces what it drew before: the ids it claimed then are free again, or its own explicit ids would
     * count as duplicates. Ids of the rest of the page still count.
     */
    private function owner(): ?string
    {
        $component = app('view')->getShared()['__livewire'] ?? null;
        if (! is_object($component) || ! method_exists($component, 'getId')) {
            return null;
        }
        $key = (string) $component->getId();
        $seen = $this->renders[$key] ?? null;
        if ($seen?->get() !== $component) {
            if ($seen !== null) {
                $this->used = array_filter($this->used, static fn (?string $owner): bool => $owner !== $key);
            }
            $this->renders[$key] = WeakReference::create($component);
        }

        return $key;
    }

    private function reserve(string $id, ?string $owner): string
    {
        $this->used[$id] = $owner;

        return $id;
    }
}
