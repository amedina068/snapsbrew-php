<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use DateTimeImmutable;

/**
 * A reusable design with layers that a snap can change.
 *
 * The list endpoint and the write endpoints return fewer fields than the read
 * endpoint. A field that the endpoint did not send is null.
 */
final readonly class Template
{
    /**
     * @param  list<Layer>  $layers
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public int $width,
        public int $height,
        public ?string $size = null,
        public array $layers = [],
        public ?int $publishedVersion = null,
        public ?bool $hasUnpublishedEdits = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            uuid: $payload->string('uuid'),
            title: $payload->string('title'),
            width: $payload->int('width'),
            height: $payload->int('height'),
            size: $payload->nullableString('size'),
            layers: array_map(Layer::fromArray(...), $payload->objects('layers')),
            publishedVersion: $payload->nullableInt('published_version'),
            hasUnpublishedEdits: $payload->has('has_unpublished_edits') ? $payload->bool('has_unpublished_edits') : null,
            createdAt: $payload->date('created_at'),
        );
    }

    /**
     * The layer with the given key, or null.
     */
    public function layer(string $key): ?Layer
    {
        foreach ($this->layers as $layer) {
            if ($layer->key === $key) {
                return $layer;
            }
        }

        return null;
    }

    public function isPublished(): bool
    {
        return $this->publishedVersion !== null;
    }
}
