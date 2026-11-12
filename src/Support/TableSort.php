<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Illuminate\Support\Arr;

/**
 * The order a table is sorted in, and the links that change it. Read from the request, keeping only the keys the
 * columns mark sortable, whatever the query string says. This draws the state and builds links; your controller still
 * checks each key against its own list before it reaches orderBy().
 */
final readonly class TableSort
{
    /**
     * @param  array<string, 'asc'|'desc'>  $order  key => direction, highest priority first
     * @param  array<array-key, mixed>  $query  the page's query string, which the links keep
     * @param  ?string  $pageParameter  the paginator's page (or cursor) parameter: a new order starts again at the first page
     * @param  ?string  $fragment  the table's id, so the page opens scrolled to it
     */
    private function __construct(
        public array $order,
        private string $sortName = 'sort',
        private string $directionName = 'direction',
        private string $url = '',
        private array $query = [],
        private ?string $pageParameter = null,
        private ?string $fragment = null,
    ) {}

    /**
     * One key, or with several, the comma lists of sort and direction side by side
     * (sort=status,amount&direction=asc,desc). Unknown keys are dropped, and each key counts once.
     *
     * @param  list<string>  $sortable  the keys that may be sorted by
     * @param  bool  $several  multi-sort: keep every key, not only the first
     */
    public static function read(array $sortable, mixed $sort, mixed $direction, bool $several = false): self
    {
        $directions = explode(',', is_string($direction) ? $direction : '');
        $order = [];
        foreach (explode(',', is_string($sort) ? $sort : '') as $i => $key) {
            if (in_array($key, $sortable, true) && ! isset($order[$key])) {
                $order[$key] = ($directions[$i] ?? null) === 'desc' ? 'desc' : 'asc';
            }
        }

        return new self($several ? $order : array_slice($order, 0, 1, true));
    }

    /**
     * The same order, with what its links need: the page's URL and query, the names of the two parameters, the
     * paginator's page parameter and the table's id.
     *
     * @param  array<array-key, mixed>  $query
     */
    public function linkedTo(string $url, array $query, string $sortName, string $directionName, ?string $pageParameter = null, ?string $fragment = null): self
    {
        return new self($this->order, $sortName, $directionName, $url, $query, $pageParameter, $fragment);
    }

    /** The column sorted by first, or null when the rows are in the order they came. */
    public function key(): ?string
    {
        return array_key_first($this->order);
    }

    /** The first column's direction; asc when nothing is sorted. */
    public function direction(): string
    {
        return $this->order[$this->key() ?? ''] ?? 'asc';
    }

    /**
     * What a plain click on the column does next, from its own state: ascending, then descending, then null for no
     * sort at all (back to the order the rows came in).
     *
     * @return 'asc'|'desc'|null
     */
    public function next(string $key): ?string
    {
        return match ($this->order[$key] ?? null) {
            null => 'asc',
            'asc' => 'desc',
            default => null,
        };
    }

    /** A plain click: sort by this column alone, in its next state. */
    public function url(string $key): string
    {
        $next = $this->next($key);

        return $this->link($next === null ? [] : [$key => $next]);
    }

    /** Shift+click with multi-sort: add the column last, or flip it, or (after descending) drop it. */
    public function addUrl(string $key): string
    {
        $order = $this->order;
        if (! isset($order[$key])) {
            $order[$key] = 'asc';
        } elseif ($order[$key] === 'asc') {
            $order[$key] = 'desc';
        } else {
            unset($order[$key]);
        }

        return $this->link($order);
    }

    /** @param  array<string, 'asc'|'desc'>  $order */
    private function link(array $order): string
    {
        $query = array_merge($this->query, [$this->sortName => implode(',', array_keys($order)), $this->directionName => implode(',', $order)]);
        if ($order === []) {
            unset($query[$this->sortName], $query[$this->directionName]);
        }
        // A new order starts again at the first page.
        if ($this->pageParameter !== null) {
            unset($query[$this->pageParameter]);
        }

        return $this->url.($query ? '?'.Arr::query($query) : '').($this->fragment ? '#'.$this->fragment : '');
    }
}
