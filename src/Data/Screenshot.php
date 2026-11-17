<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use DateTimeImmutable;

/**
 * An image of a web page.
 */
final readonly class Screenshot
{
    public function __construct(
        public string $id,
        public ?string $url,
        public ?string $imageUrl,
        public int $width,
        public int $height,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * The list endpoint sends the identifier as `uuid`. The read and the
     * create endpoints send it as `id`. Each endpoint sends `image_url`.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            id: $payload->has('id') ? $payload->string('id') : $payload->string('uuid'),
            url: $payload->nullableString('url'),
            imageUrl: $payload->nullableString('image_url'),
            width: $payload->int('width'),
            height: $payload->int('height'),
            createdAt: $payload->date('created_at'),
        );
    }
}
