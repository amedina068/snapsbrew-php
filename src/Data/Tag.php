<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * A label for templates.
 */
final readonly class Tag
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            id: $payload->int('id'),
            name: $payload->string('name'),
            slug: $payload->string('slug'),
        );
    }
}
