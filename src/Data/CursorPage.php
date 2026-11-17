<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a list that uses cursors.
 *
 * Give `$nextCursor` to the same list method to read the next page.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final readonly class CursorPage implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor = null,
        public ?string $previousCursor = null,
    ) {}

    /**
     * @template TValue
     *
     * @param  array<array-key, mixed>  $body
     * @param  callable(array<array-key, mixed>): TValue  $map
     * @return self<TValue>
     */
    public static function fromBody(array $body, callable $map): self
    {
        $meta = Payload::of(Payload::of($body)->array('meta'));

        return new self(
            array_map($map, Payload::of($body)->objects('data')),
            $meta->nullableString('next_cursor'),
            $meta->nullableString('previous_cursor'),
        );
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return TItem|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
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
