<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use ArrayAccess;
use LogicException;

/**
 * A static reference catalog, for example the design guide.
 *
 * The catalogs are large documents with a free structure, so the SDK keeps
 * the content as an array. The content is the same for every account, and
 * you can cache it.
 *
 * @implements ArrayAccess<array-key, mixed>
 */
final readonly class Guide implements ArrayAccess
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(
        public array $data,
        public ?string $specsReviewed = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $body
     */
    public static function fromBody(array $body): self
    {
        $payload = Payload::of($body);

        return new self($payload->array('data'), $payload->nullableString('specs_reviewed'));
    }

    /**
     * Read a value with "dot" notation, for example `typography.scale`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('A guide is read-only.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A guide is read-only.');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
