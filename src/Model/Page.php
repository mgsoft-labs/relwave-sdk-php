<?php

declare(strict_types=1);

namespace Relwave\Model;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A single page of a paginated list response. Iterable over its items and
 * exposes the `meta` pagination fields returned by the API.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * @param  array<int, TItem>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    /**
     * Build a page from decoded data items and the `meta` envelope block.
     *
     * @param  array<int, TItem>  $items
     * @param  array<string, mixed>  $meta
     * @return self<TItem>
     */
    public static function fromMeta(array $items, array $meta): self
    {
        return new self(
            items: $items,
            currentPage: (int) ($meta['current_page'] ?? 1),
            lastPage: (int) ($meta['last_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? count($items)),
            total: (int) ($meta['total'] ?? count($items)),
        );
    }

    /**
     * Whether another page of results exists after this one.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * The page number to request next, or null when on the last page.
     */
    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
