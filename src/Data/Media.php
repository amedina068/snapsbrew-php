<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use DateTimeImmutable;

/**
 * An image in the media library of the account.
 *
 * Use `src` as the `image_url` of a variant. The upload and the list send
 * nine fields and no other field: `id`, `uuid`, `name`, `file_name`,
 * `mime_type`, `size`, `original_url`, `src` and `created_at`. The SDK
 * requires `id`, `file_name` and `src`. The other properties are null when
 * the body does not have them.
 */
final readonly class Media
{
    public function __construct(
        public int $id,
        public string $fileName,
        public string $src,
        public ?string $uuid = null,
        public ?string $name = null,
        public ?string $mimeType = null,
        public ?int $size = null,
        public ?string $originalUrl = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            id: $payload->int('id'),
            fileName: $payload->string('file_name'),
            src: $payload->string('src'),
            uuid: $payload->nullableString('uuid'),
            name: $payload->nullableString('name'),
            mimeType: $payload->nullableString('mime_type'),
            size: $payload->nullableInt('size'),
            originalUrl: $payload->nullableString('original_url'),
            createdAt: $payload->date('created_at'),
        );
    }
}
