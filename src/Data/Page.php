<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a list that uses page numbers.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * Reads the body of a Laravel length-aware paginator. The paginator
     * fields are at the top level of the body.
     *
     * @template TValue
     *
     * @param  array<array-key, mixed>  $body
     * @param  callable(array<array-key, mixed>): TValue  $map
     * @return self<TValue>
     */
    public static function fromBody(array $body, callable $map): self
    {
        $payload = Payload::of($body);
        $meta = $payload->has('meta') ? Payload::of($payload->array('meta')) : $payload;

        return new self(
            array_map($map, $payload->objects('data')),
            $meta->int('current_page'),
            $meta->has('last_page') ? $meta->int('last_page') : $meta->int('current_page'),
            $meta->int('per_page'),
            $meta->int('total'),
        );
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * @return TItem|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function nextPage(): ?int
    {
        return $this->hasMore() ? $this->currentPage + 1 : null;
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
