<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * The result of a publish: the version that snaps and previews now render.
 */
final readonly class PublishedTemplate
{
    public function __construct(
        public string $uuid,
        public int $publishedVersion,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            uuid: $payload->string('uuid'),
            publishedVersion: $payload->int('published_version'),
        );
    }
}
