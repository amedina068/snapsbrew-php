<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * An image in the media library of the account.
 */
final readonly class Media
{
    public function __construct(
        public int $id,
        public string $fileName,
        public string $src,
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
        );
    }
}
