<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use Snapsbrew\Enums\Status;
use Snapsbrew\Exceptions\UnexpectedResponseException;

/**
 * One snap of a batch.
 *
 * The API gives each item an identifier: your name for it, or `item_1`,
 * `item_2` and so on. An item from another source can have no identifier.
 */
final readonly class BatchItem
{
    /**
     * @param  string|null  $mediaUrl  The image URL. It is null until the render completes, and after a failed render.
     * @param  string|null  $lastError  The error code `render_failed` (`Snap::ERROR_RENDER_FAILED`) after a failed render, or null.
     */
    public function __construct(
        public string $id,
        public ?string $identifier,
        public Status $status,
        public ?string $mediaUrl = null,
        public ?string $lastError = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            id: $payload->string('id'),
            identifier: $payload->nullableString('identifier'),
            status: Status::tryFrom($payload->string('status')) ?? throw UnexpectedResponseException::field('status', 'status'),
            mediaUrl: $payload->nullableString('media_url') ?: null,
            lastError: $payload->nullableString('last_error'),
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === Status::Completed;
    }
}
